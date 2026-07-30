<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\RequestAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

        $monthlyRequests = $this->baseQuery($request)
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->get();

        $yearRequests = $this->baseQuery($request)
            ->whereDate('start_date', '<=', CarbonImmutable::create($year, 12, 31)->toDateString())
            ->whereDate('end_date', '>=', CarbonImmutable::create($year, 1, 1)->toDateString())
            ->get();

        $monthlyStats = [
            'approved' => $monthlyRequests->where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'pending' => $monthlyRequests->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])->count(),
            'rejected' => $monthlyRequests->where('status', LeaveRequest::STATUS_REJECTED)->count(),
            'cancelled' => $monthlyRequests->where('status', LeaveRequest::STATUS_CANCELLED)->count(),
            'vacation_used' => $monthlyRequests
                ->filter(fn (LeaveRequest $leaveRequest) => $leaveRequest->status === LeaveRequest::STATUS_APPROVED && $leaveRequest->leaveType?->code === 'VACATIONS')
                ->sum('requested_units'),
            'medical_count' => $monthlyRequests
                ->filter(fn (LeaveRequest $leaveRequest) => $leaveRequest->leaveType?->is_medical)
                ->count(),
        ];

        $yearlyStats = [
            'approved' => $yearRequests->where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'pending' => $yearRequests->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])->count(),
            'rejected' => $yearRequests->where('status', LeaveRequest::STATUS_REJECTED)->count(),
            'vacation_used' => $yearRequests
                ->filter(fn (LeaveRequest $leaveRequest) => $leaveRequest->status === LeaveRequest::STATUS_APPROVED && $leaveRequest->leaveType?->code === 'VACATIONS')
                ->sum('requested_units'),
            'medical_count' => $yearRequests
                ->filter(fn (LeaveRequest $leaveRequest) => $leaveRequest->leaveType?->is_medical)
                ->count(),
        ];

        $byType = $monthlyRequests
            ->groupBy(fn (LeaveRequest $leaveRequest) => $leaveRequest->leaveType?->name ?? 'Sin tipo')
            ->map(fn ($group, string $name): array => [
                'name' => $name,
                'total' => $group->count(),
                'approved' => $group->where('status', LeaveRequest::STATUS_APPROVED)->count(),
                'pending' => $group->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])->count(),
                'rejected' => $group->where('status', LeaveRequest::STATUS_REJECTED)->count(),
                'units' => $group->where('status', LeaveRequest::STATUS_APPROVED)->sum('requested_units'),
            ])
            ->values();

        $pendingJustifications = LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $request->user()->organization_id)
            ->whereHas('leaveType', fn (Builder $query) => $query->where('attachment_requirement', 'required'))
            ->whereDoesntHave('attachments', fn (Builder $query) => $query->whereIn('justification_status', ['received', 'reviewed']))
            ->latest()
            ->limit(8)
            ->get();

        $recentAttachments = RequestAttachment::with(['leaveRequest.employeeProfile.user', 'leaveRequest.leaveType', 'reviewer'])
            ->where('organization_id', $request->user()->organization_id)
            ->latest()
            ->limit(8)
            ->get();

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

    private function baseQuery(Request $request): Builder
    {
        return LeaveRequest::with(['employeeProfile.user', 'leaveType'])
            ->where('organization_id', $request->user()->organization_id);
    }
}
