<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\LeaveAllowance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

class LeaveBalanceService
{
    public function allowanceFor(EmployeeProfile $employee, LeaveType $leaveType, CarbonImmutable $date): ?LeaveAllowance
    {
        if (! $leaveType->consumes_balance || ! $leaveType->balance_code) {
            return null;
        }

        return LeaveAllowance::where('employee_profile_id', $employee->id)
            ->where('balance_code', $leaveType->balance_code)
            ->whereDate('period_start', '<=', $date->toDateString())
            ->whereDate('period_end', '>=', $date->toDateString())
            ->first();
    }

    public function currentBalance(LeaveAllowance $allowance): int
    {
        return (int) $allowance->movements()->sum('amount');
    }

    public function reservedUnits(EmployeeProfile $employee, LeaveType $leaveType): int
    {
        if (! $leaveType->consumes_balance) {
            return 0;
        }

        return (int) LeaveRequest::where('employee_profile_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->sum('requested_units');
    }

    public function availableBalance(EmployeeProfile $employee, LeaveType $leaveType, CarbonImmutable $date, bool $reservePending): ?int
    {
        $allowance = $this->allowanceFor($employee, $leaveType, $date);

        if (! $allowance) {
            return null;
        }

        $balance = $this->currentBalance($allowance);

        if ($reservePending) {
            $balance -= $this->reservedUnits($employee, $leaveType);
        }

        return $balance;
    }
}
