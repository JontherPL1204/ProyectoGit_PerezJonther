<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\LeaveBalanceMovement;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovalService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly AuditService $audit,
    ) {}

    public function approve(LeaveRequest $leaveRequest, User $actor, ?string $comment = null, ?Request $httpRequest = null): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('La solicitud ya fue resuelta.');
        }

        return DB::transaction(function () use ($leaveRequest, $actor, $comment, $httpRequest): LeaveRequest {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if ($previous !== LeaveRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('La solicitud ya fue resuelta.');
            }

            $locked->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'admin_comment' => $comment,
                'version' => $locked->version + 1,
            ]);

            $this->consumeBalanceIfNeeded($locked, $actor);
            $this->audit->requestEvent($locked, 'REQUEST_APPROVED', $actor, $previous, $locked->status, $comment, [], $httpRequest);

            return $locked->fresh(['employeeProfile.user', 'leaveType']);
        });
    }

    public function reject(LeaveRequest $leaveRequest, User $actor, string $comment, ?Request $httpRequest = null): LeaveRequest
    {
        if (trim($comment) === '') {
            throw new InvalidArgumentException('El rechazo requiere comentario obligatorio.');
        }

        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('La solicitud ya fue resuelta.');
        }

        return DB::transaction(function () use ($leaveRequest, $actor, $comment, $httpRequest): LeaveRequest {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if ($previous !== LeaveRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('La solicitud ya fue resuelta.');
            }

            $locked->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'admin_comment' => $comment,
                'version' => $locked->version + 1,
            ]);

            $this->audit->requestEvent($locked, 'REQUEST_REJECTED', $actor, $previous, $locked->status, $comment, [], $httpRequest);

            return $locked->fresh(['employeeProfile.user', 'leaveType']);
        });
    }

    public function resolveCancellation(LeaveRequest $leaveRequest, User $actor, bool $accept, ?string $comment = null, ?Request $httpRequest = null): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING_CANCELLATION) {
            throw new InvalidArgumentException('La solicitud no tiene una cancelacion pendiente.');
        }

        return DB::transaction(function () use ($leaveRequest, $actor, $accept, $comment, $httpRequest): LeaveRequest {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if ($previous !== LeaveRequest::STATUS_PENDING_CANCELLATION) {
                throw new InvalidArgumentException('La cancelacion ya fue resuelta.');
            }

            $locked->update([
                'status' => $accept ? LeaveRequest::STATUS_CANCELLED : LeaveRequest::STATUS_APPROVED,
                'admin_comment' => $comment,
                'cancelled_at' => $accept ? now() : null,
                'version' => $locked->version + 1,
            ]);

            if ($accept) {
                $this->returnBalanceIfNeeded($locked, $actor);
            }

            $this->audit->requestEvent(
                $locked,
                $accept ? 'CANCELLATION_ACCEPTED' : 'CANCELLATION_REJECTED',
                $actor,
                $previous,
                $locked->status,
                $comment,
                [],
                $httpRequest,
            );

            return $locked->fresh(['employeeProfile.user', 'leaveType']);
        });
    }

    private function consumeBalanceIfNeeded(LeaveRequest $leaveRequest, User $actor): void
    {
        $leaveType = $leaveRequest->leaveType;

        if (! $leaveType->consumes_balance) {
            return;
        }

        $allowance = $this->balances->allowanceFor(
            $leaveRequest->employeeProfile,
            $leaveType,
            CarbonImmutable::parse($leaveRequest->start_date),
        );

        if (! $allowance) {
            throw new InvalidArgumentException('No existe saldo para aprobar esta solicitud.');
        }

        $settings = CompanySetting::where('organization_id', $leaveRequest->organization_id)->firstOrFail();
        $currentBalance = $this->balances->currentBalance($allowance);

        if (! $settings->allow_negative_balance && $currentBalance < $leaveRequest->requested_units) {
            throw new InvalidArgumentException('Saldo insuficiente al aprobar.');
        }

        LeaveBalanceMovement::firstOrCreate(
            ['idempotency_key' => 'approval-'.$leaveRequest->id],
            [
                'organization_id' => $leaveRequest->organization_id,
                'leave_allowance_id' => $allowance->id,
                'leave_request_id' => $leaveRequest->id,
                'movement_type' => 'CONSUMPTION',
                'amount' => -1 * $leaveRequest->requested_units,
                'reason' => 'Consumo por solicitud aprobada',
                'created_by' => $actor->id,
                'created_at' => now(),
            ],
        );
    }

    private function returnBalanceIfNeeded(LeaveRequest $leaveRequest, User $actor): void
    {
        $leaveType = $leaveRequest->leaveType;

        if (! $leaveType->consumes_balance) {
            return;
        }

        $allowance = $this->balances->allowanceFor(
            $leaveRequest->employeeProfile,
            $leaveType,
            CarbonImmutable::parse($leaveRequest->start_date),
        );

        if (! $allowance) {
            return;
        }

        LeaveBalanceMovement::firstOrCreate(
            ['idempotency_key' => 'cancellation-'.$leaveRequest->id],
            [
                'organization_id' => $leaveRequest->organization_id,
                'leave_allowance_id' => $allowance->id,
                'leave_request_id' => $leaveRequest->id,
                'movement_type' => 'RETURN',
                'amount' => $leaveRequest->requested_units,
                'reason' => 'Devolucion por cancelacion aceptada',
                'created_by' => $actor->id,
                'created_at' => now(),
            ],
        );
    }
}
