<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\NotificationService;
use App\Services\OrganizationDataCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly NotificationService $notifications,
        private readonly OrganizationDataCache $dataCache,
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

        $requests = DB::table('leave_requests')
            ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
            ->join('users', 'users.id', '=', 'employee_profiles.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.organization_id', $request->user()->organization_id)
            ->whereIn('leave_requests.status', $statusFilters[$currentFilter]['statuses'])
            ->when($advancedFilters['employee_profile_id'], fn ($query, $employeeProfileId) => $query->where('leave_requests.employee_profile_id', $employeeProfileId))
            ->when($advancedFilters['leave_type_id'], fn ($query, $leaveTypeId) => $query->where('leave_requests.leave_type_id', $leaveTypeId))
            ->when($advancedFilters['date_from'], fn ($query, $dateFrom) => $query->whereDate('leave_requests.end_date', '>=', $dateFrom))
            ->when($advancedFilters['date_to'], fn ($query, $dateTo) => $query->whereDate('leave_requests.start_date', '<=', $dateTo))
            ->select([
                'leave_requests.id',
                'leave_requests.organization_id',
                'leave_requests.employee_profile_id',
                'leave_requests.status',
                'leave_requests.unit',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.requested_units',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
                'leave_types.approval_level_count',
            ])
            ->orderBy('leave_requests.start_date')
            ->orderByDesc('leave_requests.created_at')
            ->simplePaginate(12)
            ->withQueryString();

        $requests->getCollection()->transform(fn ($row) => $this->formatRequestRow($row));
        $this->attachApprovalProgress($requests->getCollection(), $request->user());
        $this->attachOverlapWarnings($requests->getCollection());

        $stats = $this->dataCache->requestStatusCounts($request->user()->organization_id);

        $employees = $this->dataCache->employees($request->user()->organization_id);
        $leaveTypes = $this->dataCache->leaveTypes($request->user()->organization_id);

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

            if ($updated->status === LeaveRequest::STATUS_APPROVED) {
                $this->notifications->requestResolved($updated, 'REQUEST_APPROVED');
            }

            $this->dataCache->forgetRequestData($request->user()->organization_id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('status', $updated->status === LeaveRequest::STATUS_APPROVED
            ? 'Solicitud aprobada.'
            : 'Nivel aprobado. La solicitud sigue pendiente del siguiente nivel.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeAdminForRequest($request, $leaveRequest);

        $data = $request->validate(['admin_comment' => ['required', 'string', 'max:1000']]);

        try {
            $updated = $this->approvals->reject($leaveRequest, $request->user(), $data['admin_comment'], $request);
            $this->notifications->requestResolved($updated, 'REQUEST_REJECTED');
            $this->dataCache->forgetRequestData($request->user()->organization_id);
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
            $this->dataCache->forgetRequestData($request->user()->organization_id);
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
            $this->dataCache->forgetRequestData($request->user()->organization_id);
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
        if ($requests->isEmpty()) {
            return;
        }

        $rangeStart = $requests->min(fn ($leaveRequest) => $leaveRequest->start_date->toDateString());
        $rangeEnd = $requests->max(fn ($leaveRequest) => $leaveRequest->end_date->toDateString());
        $requestIds = $requests->pluck('id')->all();

        $overlaps = DB::table('leave_requests')
            ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
            ->join('users', 'users.id', '=', 'employee_profiles.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.organization_id', $requests->first()->organization_id)
            ->whereNotIn('leave_requests.id', $requestIds)
            ->whereIn('leave_requests.status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_PENDING_CANCELLATION,
                LeaveRequest::STATUS_APPROVED,
            ])
            ->whereDate('leave_requests.start_date', '<=', $rangeEnd)
            ->whereDate('leave_requests.end_date', '>=', $rangeStart)
            ->select([
                'leave_requests.id',
                'leave_requests.employee_profile_id',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
            ])
            ->orderBy('leave_requests.start_date')
            ->get()
            ->map(fn ($row) => $this->formatRequestRow($row, false));

        foreach ($requests as $leaveRequest) {
            $leaveRequest->overlap_warnings = $overlaps
                ->filter(fn ($overlap) => $overlap->employee_profile_id !== $leaveRequest->employee_profile_id
                    && $overlap->start_date->lessThanOrEqualTo($leaveRequest->end_date)
                    && $overlap->end_date->greaterThanOrEqualTo($leaveRequest->start_date))
                ->take(3)
                ->values();
        }
    }

    private function attachApprovalProgress(Collection $requests, User $actor): void
    {
        if ($requests->isEmpty()) {
            return;
        }

        $steps = DB::table('approval_steps')
            ->leftJoin('users', 'users.id', '=', 'approval_steps.decided_by')
            ->whereIn('approval_steps.leave_request_id', $requests->pluck('id')->all())
            ->select([
                'approval_steps.leave_request_id',
                'approval_steps.level',
                'approval_steps.status',
                'approval_steps.comment',
                'approval_steps.decided_at',
                'users.name as decided_by_name',
            ])
            ->orderBy('approval_steps.level')
            ->get()
            ->groupBy('leave_request_id');

        foreach ($requests as $leaveRequest) {
            $requestSteps = $steps->get($leaveRequest->id, collect());
            $configuredTotal = max(1, min(3, (int) ($leaveRequest->approval_level_count ?: 1)));
            $storedTotal = (int) ($requestSteps->max('level') ?: 0);
            $total = max($configuredTotal, $storedTotal, 1);
            $pendingStep = $requestSteps->firstWhere('status', ApprovalStep::STATUS_PENDING);
            $currentLevel = $pendingStep
                ? (int) $pendingStep->level
                : ($leaveRequest->status === LeaveRequest::STATUS_PENDING ? 1 : null);

            $leaveRequest->approval_steps = $requestSteps;
            $leaveRequest->approval_total = $total;
            $leaveRequest->approval_current_level = $currentLevel;
            $leaveRequest->can_resolve_current_level = $currentLevel !== null
                && $leaveRequest->status === LeaveRequest::STATUS_PENDING
                && $this->approvals->canApproveLevel($actor, $currentLevel);
            $leaveRequest->approval_summary = $this->approvalSummary($leaveRequest, $currentLevel, $total);
        }
    }

    private function approvalSummary(object $leaveRequest, ?int $currentLevel, int $total): string
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            return $total > 1 ? 'Revision completa' : 'Revision simple';
        }

        if ($total <= 1) {
            return 'Revision simple';
        }

        return $currentLevel
            ? 'Revision '.$currentLevel.' de '.$total
            : 'Aprobaciones completas; falta cierre';
    }

    private function formatRequestRow(object $row, bool $withStatus = true): object
    {
        $row->start_date = CarbonImmutable::parse($row->start_date);
        $row->end_date = CarbonImmutable::parse($row->end_date);

        if ($withStatus) {
            $row->status_label = $this->statusLabel($row->status);
        }

        return $row;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            LeaveRequest::STATUS_PENDING => 'Pendiente',
            LeaveRequest::STATUS_APPROVED => 'Aprobada',
            LeaveRequest::STATUS_REJECTED => 'Rechazada',
            LeaveRequest::STATUS_CANCELLED => 'Cancelada',
            LeaveRequest::STATUS_PENDING_CANCELLATION => 'Cancelacion pendiente',
            default => $status,
        };
    }
}
