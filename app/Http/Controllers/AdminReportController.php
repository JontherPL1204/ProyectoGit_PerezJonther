<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $year = $request->integer('anio') ?: (int) now()->format('Y');
        $month = $request->integer('mes') ?: (int) now()->format('m');

        if ($year < 2020 || $year > 2100) {
            $year = (int) now()->format('Y');
        }

        if ($month < 1 || $month > 12) {
            $month = (int) now()->format('m');
        }

        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $report = Cache::remember(
            'admin-report:'.$request->user()->organization_id.':'.$year.':'.$month.':v1',
            60,
            fn (): array => $this->reportData($request, $monthStart, $monthEnd, $year),
        );

        $monthlyStats = $report['monthlyStats'];
        $yearlyStats = $report['yearlyStats'];
        $byType = collect($report['byType']);
        $pendingJustifications = collect($report['pendingJustifications'])
            ->map(function (array $row) {
                $row['start_date'] = CarbonImmutable::parse($row['start_date']);

                return (object) $row;
            });
        $recentAttachments = collect($report['recentAttachments'])
            ->map(function (array $row) {
                $row['created_at'] = CarbonImmutable::parse($row['created_at']);

                return (object) $row;
            });

        return view('admin.reports', compact(
            'byType',
            'month',
            'monthlyStats',
            'monthStart',
            'pendingJustifications',
            'recentAttachments',
            'year',
            'yearlyStats',
        ));
    }

    private function reportData(Request $request, CarbonImmutable $monthStart, CarbonImmutable $monthEnd, int $year): array
    {
        $pendingJustifications = DB::table('leave_requests')
            ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
            ->join('users', 'users.id', '=', 'employee_profiles.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.organization_id', $request->user()->organization_id)
            ->where('leave_types.attachment_requirement', 'required')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('request_attachments')
                    ->whereColumn('request_attachments.leave_request_id', 'leave_requests.id')
                    ->whereNull('request_attachments.deleted_at')
                    ->whereIn('request_attachments.justification_status', ['received', 'reviewed']);
            })
            ->select([
                'leave_requests.id',
                'leave_requests.start_date',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
            ])
            ->orderByDesc('leave_requests.created_at')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        $recentAttachments = DB::table('request_attachments')
            ->leftJoin('leave_requests', 'leave_requests.id', '=', 'request_attachments.leave_request_id')
            ->leftJoin('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_profile_id')
            ->leftJoin('users', 'users.id', '=', 'employee_profiles.user_id')
            ->leftJoin('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('request_attachments.deleted_at')
            ->where('request_attachments.organization_id', $request->user()->organization_id)
            ->select([
                'request_attachments.id',
                'request_attachments.leave_request_id',
                'request_attachments.original_name',
                'request_attachments.justification_status',
                'request_attachments.created_at',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
            ])
            ->orderByDesc('request_attachments.created_at')
            ->limit(8)
            ->get()
            ->map(function ($row): array {
                $row->justification_label = $this->justificationLabel($row->justification_status);

                return (array) $row;
            })
            ->all();

        return [
            'monthlyStats' => $this->statsForPeriod($request, $monthStart, $monthEnd),
            'yearlyStats' => $this->statsForPeriod(
                $request,
                CarbonImmutable::create($year, 1, 1)->startOfDay(),
                CarbonImmutable::create($year, 12, 31)->startOfDay(),
            ),
            'byType' => $this->byTypeForPeriod($request, $monthStart, $monthEnd)->all(),
            'pendingJustifications' => $pendingJustifications,
            'recentAttachments' => $recentAttachments,
        ];
    }

    /**
     * @return array<string,int>
     */
    private function statsForPeriod(Request $request, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $row = DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.organization_id', $request->user()->organization_id)
            ->whereDate('leave_requests.start_date', '<=', $periodEnd->toDateString())
            ->whereDate('leave_requests.end_date', '>=', $periodStart->toDateString())
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? THEN 1 ELSE 0 END) as approved', [LeaveRequest::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN leave_requests.status IN (?, ?) THEN 1 ELSE 0 END) as pending', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? THEN 1 ELSE 0 END) as rejected', [LeaveRequest::STATUS_REJECTED])
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? THEN 1 ELSE 0 END) as cancelled', [LeaveRequest::STATUS_CANCELLED])
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? AND leave_types.code = ? THEN leave_requests.requested_units ELSE 0 END) as vacation_used', [LeaveRequest::STATUS_APPROVED, 'VACATIONS'])
            ->selectRaw('SUM(CASE WHEN leave_types.is_medical = ? THEN 1 ELSE 0 END) as medical_count', [true])
            ->first();

        return [
            'approved' => (int) ($row->approved ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'vacation_used' => (int) ($row->vacation_used ?? 0),
            'medical_count' => (int) ($row->medical_count ?? 0),
        ];
    }

    private function byTypeForPeriod(Request $request, CarbonImmutable $periodStart, CarbonImmutable $periodEnd)
    {
        return DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->whereNull('leave_requests.deleted_at')
            ->where('leave_requests.organization_id', $request->user()->organization_id)
            ->whereDate('leave_requests.start_date', '<=', $periodEnd->toDateString())
            ->whereDate('leave_requests.end_date', '>=', $periodStart->toDateString())
            ->groupBy('leave_types.name')
            ->orderBy('leave_types.name')
            ->select('leave_types.name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? THEN 1 ELSE 0 END) as approved', [LeaveRequest::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN leave_requests.status IN (?, ?) THEN 1 ELSE 0 END) as pending', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? THEN 1 ELSE 0 END) as rejected', [LeaveRequest::STATUS_REJECTED])
            ->selectRaw('SUM(CASE WHEN leave_requests.status = ? THEN leave_requests.requested_units ELSE 0 END) as units', [LeaveRequest::STATUS_APPROVED])
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'total' => (int) $row->total,
                'approved' => (int) $row->approved,
                'pending' => (int) $row->pending,
                'rejected' => (int) $row->rejected,
                'units' => (int) $row->units,
            ]);
    }

    private function justificationLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente de justificar',
            'reviewed' => 'Justificante revisado',
            default => 'Justificante recibido',
        };
    }
}
