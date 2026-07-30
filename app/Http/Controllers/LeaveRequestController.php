<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AttachmentService;
use App\Services\LeaveRequestService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequests,
        private readonly NotificationService $notifications,
        private readonly AttachmentService $attachments,
    ) {}

    public function create(Request $request): View
    {
        $types = LeaveType::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->where('visible_to_employees', true)
            ->orderBy('position')
            ->get();

        return view('leave_requests.create', ['types' => $types]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'user_comment' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $user = $request->user();
        $profile = $user->employeeProfile()->firstOrFail();
        $type = LeaveType::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->findOrFail($validated['leave_type_id']);

        try {
            $leaveRequest = $this->leaveRequests->create($profile, $type, $validated, $user, $request);
            $this->attachments->storeMany($leaveRequest->load('leaveType'), $request->file('attachments', []), $user);
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
            $this->notifications->requestResolved($updated->load(['employeeProfile.user', 'leaveType']), 'CANCELLATION_REQUESTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancelacion solicitada al administrador.');
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
}
