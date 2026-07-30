<?php

namespace App\Http\Controllers;

use App\Models\LeaveAllowance;
use App\Models\LeaveRequest;
use App\Services\LeaveBalanceService;
use App\Services\OrganizationDataCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly OrganizationDataCache $dataCache,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profileId = $this->currentEmployeeProfileId($request);

        $data = Cache::remember(
            $this->dashboardCacheKey($user->organization_id, $profileId, $user->isAdmin()),
            60,
            fn (): array => $this->dashboardData($user->organization_id, $profileId, $user->isAdmin()),
        );

        return view('dashboard', [
            'settings' => new Fluent($data['settings']),
            'vacationAllowance' => $data['vacationAllowance'] ? new Fluent($data['vacationAllowance']) : null,
            'vacationBalance' => $data['vacationBalance'],
            'requests' => collect($data['requests'])->map(fn (array $row) => $this->hydrateRequestRow($row)),
            'nextApproved' => $data['nextApproved'] ? $this->hydrateRequestRow($data['nextApproved']) : null,
            'pendingCount' => $data['pendingCount'],
            'pendingReviewRequests' => collect($data['pendingReviewRequests'])->map(fn (array $row) => $this->hydrateRequestRow($row)),
        ]);
    }

    private function dashboardData(int $organizationId, int $profileId, bool $isAdmin): array
    {
        $settings = $this->dataCache->settings($organizationId);

        $vacationAllowance = LeaveAllowance::where('employee_profile_id', $profileId)
            ->where('balance_code', 'VACATIONS')
            ->latest('period_start')
            ->withSum('movements', 'amount')
            ->first();

        $requests = DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.employee_profile_id', $profileId)
            ->select([
                'leave_requests.id',
                'leave_requests.status',
                'leave_requests.unit',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.requested_units',
                'leave_requests.created_at',
                'leave_types.name as leave_type_name',
            ])
            ->orderByDesc('leave_requests.created_at')
            ->limit(8)
            ->get()
            ->map(fn ($row) => $this->formatRequestRow($row))
            ->map(fn ($row) => (array) $row)
            ->all();

        $nextApproved = DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.employee_profile_id', $profileId)
            ->where('leave_requests.status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('leave_requests.start_date', '>=', now()->toDateString())
            ->select([
                'leave_requests.id',
                'leave_requests.status',
                'leave_requests.unit',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.requested_units',
                'leave_types.name as leave_type_name',
            ])
            ->orderBy('leave_requests.start_date')
            ->first();

        if ($nextApproved) {
            $nextApproved = $this->formatRequestRow($nextApproved);
        }

        $pendingCount = 0;
        $pendingReviewRequests = collect();

        if ($isAdmin) {
            $stats = $this->dataCache->requestStatusCounts($organizationId);
            $pendingCount = $stats['pending'] + $stats['pending_cancellation'];
            $pendingReviewRequests = DB::table('leave_requests')
                ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
                ->join('users', 'users.id', '=', 'employee_profiles.user_id')
                ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
                ->whereNull('leave_requests.deleted_at')
                ->where('leave_requests.organization_id', $organizationId)
                ->whereIn('leave_requests.status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])
                ->select([
                    'leave_requests.id',
                    'leave_requests.status',
                    'leave_requests.unit',
                    'leave_requests.start_date',
                    'leave_requests.end_date',
                    'leave_requests.requested_units',
                    'users.name as employee_name',
                    'leave_types.name as leave_type_name',
                ])
                ->orderBy('leave_requests.start_date')
                ->limit(5)
                ->get()
                ->map(fn ($row) => $this->formatRequestRow($row))
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        return [
            'settings' => $settings->toArray(),
            'vacationAllowance' => $vacationAllowance?->only(['id', 'assigned_units']),
            'vacationBalance' => $vacationAllowance ? $this->balances->currentBalance($vacationAllowance) : 0,
            'requests' => $requests,
            'nextApproved' => $nextApproved ? (array) $nextApproved : null,
            'pendingCount' => $pendingCount,
            'pendingReviewRequests' => $pendingReviewRequests,
        ];
    }

    private function hydrateRequestRow(array $row): object
    {
        $row['start_date'] = CarbonImmutable::parse($row['start_date']);
        $row['end_date'] = CarbonImmutable::parse($row['end_date']);

        return (object) $row;
    }

    private function formatRequestRow(object $row): object
    {
        $row->start_date = CarbonImmutable::parse($row->start_date)->toDateString();
        $row->end_date = CarbonImmutable::parse($row->end_date)->toDateString();
        $row->status_label = $this->statusLabel($row->status);

        return $row;
    }

    private function dashboardCacheKey(int $organizationId, int $profileId, bool $isAdmin): string
    {
        return 'dashboard-data:'.$organizationId.':'.$profileId.':'.($isAdmin ? 'admin' : 'user').':v1';
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
