<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaveRequestService
{
    public function __construct(
        private readonly LeaveCalculationService $calculator,
        private readonly LeaveBalanceService $balances,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function create(EmployeeProfile $employee, LeaveType $leaveType, array $data, User $actor, ?Request $httpRequest = null): LeaveRequest
    {
        $settings = CompanySetting::where('organization_id', $employee->organization_id)->firstOrFail();
        $start = CarbonImmutable::parse($data['start_date']);

        if (! $leaveType->allow_retroactive && $start->isPast() && ! $start->isToday()) {
            throw new InvalidArgumentException('No se permiten solicitudes retroactivas para este motivo.');
        }

        if ($leaveType->notice_value > 0 && now()->startOfDay()->diffInDays($start, false) < $leaveType->notice_value) {
            throw new InvalidArgumentException('Este motivo requiere al menos '.$leaveType->notice_value.' dias naturales de anticipacion.');
        }

        $calculation = $this->calculator->calculate(
            $employee,
            $leaveType,
            $data['start_date'],
            $data['end_date'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
        );

        if ($calculation['units'] <= 0) {
            throw new InvalidArgumentException('La solicitud no contiene dias u horas computables.');
        }

        if ($leaveType->min_units !== null && $calculation['units'] < $leaveType->min_units) {
            throw new InvalidArgumentException('La duracion es menor que el minimo configurado.');
        }

        if ($leaveType->max_units !== null && $calculation['units'] > $leaveType->max_units) {
            throw new InvalidArgumentException('La duracion supera el maximo configurado.');
        }

        $this->assertNoOverlap($employee, $data['start_date'], $data['end_date']);

        if ($leaveType->consumes_balance) {
            $available = $this->balances->availableBalance($employee, $leaveType, $start, $settings->pending_requests_reserve_balance);

            if ($available === null) {
                throw new InvalidArgumentException('No existe una bolsa de saldo para este periodo.');
            }

            if (! $settings->allow_negative_balance && $available < $calculation['units']) {
                throw new InvalidArgumentException('Saldo insuficiente para esta solicitud.');
            }
        }

        return DB::transaction(function () use ($employee, $leaveType, $data, $actor, $httpRequest, $calculation): LeaveRequest {
            $leaveRequest = LeaveRequest::create([
                'organization_id' => $employee->organization_id,
                'employee_profile_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'unit' => $leaveType->unit,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'requested_units' => $calculation['units'],
                'status' => LeaveRequest::STATUS_PENDING,
                'user_comment' => $data['user_comment'] ?? null,
            ]);

            foreach ($calculation['rows'] as $row) {
                $leaveRequest->calculationDays()->create($row);
            }

            $this->audit->requestEvent(
                $leaveRequest,
                'REQUEST_CREATED',
                $actor,
                null,
                LeaveRequest::STATUS_PENDING,
                $data['user_comment'] ?? null,
                ['requested_units' => $calculation['units']],
                $httpRequest,
            );

            return $leaveRequest;
        });
    }

    public function cancelPending(LeaveRequest $leaveRequest, User $actor, ?Request $httpRequest = null): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Solo se pueden cancelar directamente solicitudes pendientes.');
        }

        return DB::transaction(function () use ($leaveRequest, $actor, $httpRequest): LeaveRequest {
            $previous = $leaveRequest->status;
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'version' => $leaveRequest->version + 1,
            ]);

            $this->audit->requestEvent($leaveRequest, 'REQUEST_CANCELLED', $actor, $previous, $leaveRequest->status, null, [], $httpRequest);

            return $leaveRequest;
        });
    }

    public function requestCancellation(LeaveRequest $leaveRequest, User $actor, ?Request $httpRequest = null): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_APPROVED) {
            throw new InvalidArgumentException('Solo se puede solicitar cancelacion de solicitudes aprobadas.');
        }

        return DB::transaction(function () use ($leaveRequest, $actor, $httpRequest): LeaveRequest {
            $previous = $leaveRequest->status;
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_PENDING_CANCELLATION,
                'requested_cancel_at' => now(),
                'version' => $leaveRequest->version + 1,
            ]);

            $this->audit->requestEvent($leaveRequest, 'CANCELLATION_REQUESTED', $actor, $previous, $leaveRequest->status, null, [], $httpRequest);

            return $leaveRequest;
        });
    }

    private function assertNoOverlap(EmployeeProfile $employee, string $startDate, string $endDate): void
    {
        $overlap = LeaveRequest::where('employee_profile_id', $employee->id)
            ->whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_APPROVED,
                LeaveRequest::STATUS_PENDING_CANCELLATION,
            ])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException('Ya existe una solicitud activa que se solapa con esas fechas.');
        }
    }
}
