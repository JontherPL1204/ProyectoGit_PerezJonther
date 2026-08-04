<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalService;
use App\Services\AttachmentService;
use App\Services\LeaveRequestService;
use App\Services\NotificationService;
use App\Services\OrganizationDataCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequests,
        private readonly ApprovalService $approvals,
        private readonly NotificationService $notifications,
        private readonly AttachmentService $attachments,
        private readonly OrganizationDataCache $dataCache,
    ) {}

    public function create(Request $request): View
    {
        $profile = $request->user()->employeeProfile()->firstOrFail();

        $types = $this->dataCache->leaveTypes($request->user()->organization_id)
            ->where('is_active', true)
            ->where('visible_to_employees', true)
            ->filter(function ($type) use ($profile): bool {
                return $type->department_id === null || (int) $type->department_id === (int) $profile->department_id;
            })
            ->values();

        return view('leave_requests.create', ['types' => $types]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'duration_unit' => ['nullable', 'in:DAYS,MINUTES'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'user_comment' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'leave_type_id.required' => 'Selecciona el motivo de la solicitud.',
            'leave_type_id.exists' => 'El motivo seleccionado ya no esta disponible.',
            'duration_unit.in' => 'Selecciona si el permiso medico sera por dias o por horas.',
            'start_date.required' => 'Indica la fecha de inicio.',
            'start_date.date' => 'La fecha de inicio no es valida.',
            'end_date.required' => 'Indica la fecha de fin.',
            'end_date.date' => 'La fecha de fin no es valida.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'start_time.date_format' => 'La hora de inicio debe tener formato HH:MM.',
            'end_time.date_format' => 'La hora de fin debe tener formato HH:MM.',
            'attachments.max' => 'Puedes adjuntar hasta 5 archivos por solicitud.',
            'attachments.*.mimes' => 'Los adjuntos deben ser JPG, PNG o PDF.',
            'attachments.*.max' => 'Cada adjunto puede pesar hasta 5 MB.',
        ]);

        $user = $request->user();
        $profile = $user->employeeProfile()->firstOrFail();
        $type = LeaveType::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->where('visible_to_employees', true)
            ->findOrFail($validated['leave_type_id']);

        if ($type->is_medical) {
            $this->applyMedicalDurationMode($type, $validated);
        } elseif ($type->unit === 'DAYS') {
            $validated['start_time'] = null;
            $validated['end_time'] = null;
        }

        try {
            if ($type->attachment_requirement === 'required' && ! $request->hasFile('attachments')) {
                throw ValidationException::withMessages([
                    'attachments' => 'Este motivo requiere adjuntar el justificante antes de enviar la solicitud.',
                ]);
            }

            $leaveRequest = $this->leaveRequests->create($profile, $type, $validated, $user, $request);
            $this->attachments->storeMany($leaveRequest->load('leaveType'), $request->file('attachments', []), $user);
            $this->dataCache->forgetRequestData($user->organization_id);

            if ($type->auto_approve || ! $type->requires_approval) {
                $leaveRequest = $this->approvals->approve(
                    $leaveRequest->load(['employeeProfile.user', 'leaveType']),
                    $user,
                    'Aprobacion automatica por regla.',
                    $request,
                );
                $this->notifications->requestResolved($leaveRequest, 'REQUEST_APPROVED');

                return redirect()->route('leave-requests.show', $leaveRequest)
                    ->with('status', 'Solicitud creada y aprobada automaticamente.');
            }

            $this->notifications->requestCreated($leaveRequest->load(['employeeProfile.user', 'leaveType']));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['request' => $exception->getMessage()]);
        }

        return redirect()->route('leave-requests.show', $leaveRequest)
            ->with('status', 'Solicitud creada correctamente.');
    }

    public function show(Request $request, LeaveRequest $leaveRequest): View
    {
        $this->authorizeView($request, $leaveRequest);

        return view('leave_requests.show', [
            'leaveRequest' => $leaveRequest->load([
                'employeeProfile.user',
                'leaveType',
                'calculationDays',
                'approvalSteps.decidedBy',
                'events.actor',
                'attachments',
            ]),
        ]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeOwner($request, $leaveRequest);

        try {
            $this->leaveRequests->cancelPending($leaveRequest, $request->user(), $request);
            $this->dataCache->forgetRequestData($request->user()->organization_id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Solicitud cancelada.');
    }

    public function requestCancellation(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeOwner($request, $leaveRequest);

        try {
            $updated = $this->leaveRequests->requestCancellation($leaveRequest, $request->user(), $request);
            $this->notifications->cancellationRequested($updated->load(['employeeProfile.user', 'leaveType']));
            $this->dataCache->forgetRequestData($request->user()->organization_id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancelacion enviada para revision.');
    }

    private function authorizeView(Request $request, LeaveRequest $leaveRequest): void
    {
        $user = $request->user();

        abort_unless($leaveRequest->organization_id === $user->organization_id, 404);
        abort_unless($user->isAdmin() || $leaveRequest->employee_profile_id === $user->employeeProfile?->id, 403);
    }

    private function authorizeOwner(Request $request, LeaveRequest $leaveRequest): void
    {
        $user = $request->user();

        abort_unless($leaveRequest->organization_id === $user->organization_id, 404);
        abort_unless($leaveRequest->employee_profile_id === $user->employeeProfile?->id, 403);
    }

    /**
     * @param  array<string,mixed>  $validated
     */
    private function applyMedicalDurationMode(LeaveType $type, array &$validated): void
    {
        $durationUnit = $validated['duration_unit'] ?? 'DAYS';

        if ($durationUnit === 'MINUTES') {
            if (blank($validated['start_time'] ?? null) || blank($validated['end_time'] ?? null)) {
                throw ValidationException::withMessages([
                    'start_time' => 'Para un permiso medico por horas, indica hora de inicio y hora de fin.',
                ]);
            }

            $validated['end_date'] = $validated['start_date'];
            $type->unit = 'MINUTES';
            $type->min_units = 30;
            $type->max_units = 480;

            return;
        }

        $validated['start_time'] = null;
        $validated['end_time'] = null;
        $type->unit = 'DAYS';
        $type->min_units = 1;
        $type->max_units = null;
    }
}
