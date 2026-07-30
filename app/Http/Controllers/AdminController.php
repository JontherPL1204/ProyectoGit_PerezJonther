<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $statusFilters = [
            'pendientes' => [
                'label' => 'Pendientes',
                'statuses' => [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION],
            ],
            'aprobadas' => [
                'label' => 'Aprobadas',
                'statuses' => [LeaveRequest::STATUS_APPROVED],
            ],
            'rechazadas' => [
                'label' => 'Rechazadas',
                'statuses' => [LeaveRequest::STATUS_REJECTED],
            ],
            'canceladas' => [
                'label' => 'Canceladas',
                'statuses' => [LeaveRequest::STATUS_CANCELLED],
            ],
            'todas' => [
                'label' => 'Todas',
                'statuses' => [
                    LeaveRequest::STATUS_PENDING,
                    LeaveRequest::STATUS_PENDING_CANCELLATION,
                    LeaveRequest::STATUS_APPROVED,
                    LeaveRequest::STATUS_REJECTED,
                    LeaveRequest::STATUS_CANCELLED,
                ],
            ],
        ];
        $statusKey = $request->query('estado');
        $currentFilter = is_string($statusKey) && array_key_exists($statusKey, $statusFilters)
            ? $statusKey
            : 'pendientes';

        $advancedFilters = [
            'employee_profile_id' => $request->integer('empleado') ?: null,
            'leave_type_id' => $request->integer('tipo') ?: null,
            'date_from' => $this->validDateFilter($request->query('desde')),
            'date_to' => $this->validDateFilter($request->query('hasta')),
        ];

        $requests = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('status', $statusFilters[$currentFilter]['statuses'])
            ->when($advancedFilters['employee_profile_id'], fn ($query, $employeeProfileId) => $query->where('employee_profile_id', $employeeProfileId))
            ->when($advancedFilters['leave_type_id'], fn ($query, $leaveTypeId) => $query->where('leave_type_id', $leaveTypeId))
            ->when($advancedFilters['date_from'], fn ($query, $dateFrom) => $query->whereDate('end_date', '>=', $dateFrom))
            ->when($advancedFilters['date_to'], fn ($query, $dateTo) => $query->whereDate('start_date', '<=', $dateTo))
            ->orderBy('start_date')
            ->latest()
            ->get();

        $this->attachOverlapWarnings($requests);

        $stats = [
            'pending' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count(),
            'approved' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->count(),
            'pending_cancellation' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_PENDING_CANCELLATION)
                ->count(),
            'rejected' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_REJECTED)
                ->count(),
            'cancelled' => LeaveRequest::where('organization_id', $request->user()->organization_id)
                ->where('status', LeaveRequest::STATUS_CANCELLED)
                ->count(),
        ];

        $employees = EmployeeProfile::with('user')
            ->where('organization_id', $request->user()->organization_id)
            ->get()
            ->sortBy(fn (EmployeeProfile $profile) => $profile->user?->name ?? '');

        $leaveTypes = LeaveType::where('organization_id', $request->user()->organization_id)
            ->orderBy('position')
            ->get();

        return view('admin.dashboard', compact(
            'advancedFilters',
            'currentFilter',
            'employees',
            'leaveTypes',
            'requests',
            'stats',
            'statusFilters',
        ));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['nullable', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->approve($leaveRequest, $request->user(), $data['admin_comment'] ?? null, $request);
            $this->notifications->requestResolved($updated, 'REQUEST_APPROVED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Solicitud aprobada.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['required', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->reject($leaveRequest, $request->user(), $data['admin_comment'], $request);
            $this->notifications->requestResolved($updated, 'REQUEST_REJECTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Solicitud rechazada.');
    }

    public function acceptCancellation(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['nullable', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->resolveCancellation($leaveRequest, $request->user(), true, $data['admin_comment'] ?? null, $request);
            $this->notifications->requestResolved($updated, 'CANCELLATION_ACCEPTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancelacion aceptada.');
    }

    public function rejectCancellation(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['required', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->resolveCancellation($leaveRequest, $request->user(), false, $data['admin_comment'], $request);
            $this->notifications->requestResolved($updated, 'CANCELLATION_REJECTED');
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancelacion rechazada.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function authorizeAdminForRequest(Request $request, LeaveRequest $leaveRequest): void
    {
        $this->authorizeAdmin($request);
        abort_unless($leaveRequest->organization_id === $request->user()->organization_id, 404);
    }

    private function validDateFilter(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return null;
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return $value;
    }

    private function attachOverlapWarnings(Collection $requests): void
    {
        foreach ($requests as $leaveRequest) {
            $overlaps = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
                ->where('organization_id', $leaveRequest->organization_id)
                ->whereKeyNot($leaveRequest->id)
                ->where('employee_profile_id', '!=', $leaveRequest->employee_profile_id)
                ->whereIn('status', [
                    LeaveRequest::STATUS_PENDING,
                    LeaveRequest::STATUS_PENDING_CANCELLATION,
                    LeaveRequest::STATUS_APPROVED,
                ])
                ->whereDate('start_date', '<=', $leaveRequest->end_date)
                ->whereDate('end_date', '>=', $leaveRequest->start_date)
                ->orderBy('start_date')
                ->limit(3)
                ->get();

            $leaveRequest->setAttribute('overlap_warnings', $overlaps);
        }
    }
}
