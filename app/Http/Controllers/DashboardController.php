<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->employeeProfile()->first();

        abort_unless($profile, 403, 'El usuario no tiene perfil de empleado.');

        $vacationAllowance = $profile->allowances()
            ->where('balance_code', 'VACATIONS')
            ->latest('period_start')
            ->withSum('movements', 'amount')
            ->first();

        $requests = $profile->leaveRequests()
            ->with('leaveType')
            ->latest()
            ->limit(8)
            ->get();

        $nextApproved = $profile->leaveRequests()
            ->with('leaveType')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->first();

        $pendingCount = LeaveRequest::where('organization_id', $user->organization_id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_PENDING_CANCELLATION])
            ->count();

        return view('dashboard', [
            'profile' => $profile,
            'vacationAllowance' => $vacationAllowance,
            'vacationBalance' => $vacationAllowance ? $this->balances->currentBalance($vacationAllowance) : 0,
            'requests' => $requests,
            'nextApproved' => $nextApproved,
            'pendingCount' => $pendingCount,
        ]);
    }
}
