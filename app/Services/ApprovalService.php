<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\CompanySetting;
use App\Models\LeaveBalanceMovement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
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
            $locked = LeaveRequest::with('leaveType')->whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if ($previous !== LeaveRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('La solicitud ya fue resuelta.');
            }

            $this->ensureApprovalSteps($locked);
            $currentStep = $locked->approvalSteps()
                ->where('status', ApprovalStep::STATUS_PENDING)
                ->orderBy('level')
                ->lockForUpdate()
                ->first();

            if ($currentStep) {
                $this->assertCanApproveLevel($actor, (int) $currentStep->level);

                $currentStep->update([
                    'status' => ApprovalStep::STATUS_APPROVED,
                    'decided_by' => $actor->id,
                    'comment' => $comment,
                    'decided_at' => now(),
                ]);

                $this->audit->requestEvent(
                    $locked,
                    'APPROVAL_LEVEL_APPROVED',
                    $actor,
                    $previous,
                    $previous,
                    $comment,
                    [
                        'level' => $currentStep->level,
                        'total_levels' => $this->approvalStepCount($locked),
                    ],
                    $httpRequest,
                );

                if ($locked->approvalSteps()->where('status', ApprovalStep::STATUS_PENDING)->exists()) {
                    $locked->update([
                        'admin_comment' => filled($comment) ? $comment : $locked->admin_comment,
                        'version' => $locked->version + 1,
                    ]);

                    return $locked->fresh(['employeeProfile.user', 'leaveType', 'approvalSteps.decidedBy']);
                }
            }

            $locked->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'admin_comment' => filled($comment) ? $comment : $locked->admin_comment,
                'version' => $locked->version + 1,
            ]);

            $this->consumeBalanceIfNeeded($locked, $actor);
            $this->audit->requestEvent($locked, 'REQUEST_APPROVED', $actor, $previous, $locked->status, $comment, [], $httpRequest);

            return $locked->fresh(['employeeProfile.user', 'leaveType', 'approvalSteps.decidedBy']);
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
            $locked = LeaveRequest::with('leaveType')->whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if ($previous !== LeaveRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('La solicitud ya fue resuelta.');
            }

            $this->ensureApprovalSteps($locked);
            $currentStep = $locked->approvalSteps()
                ->where('status', ApprovalStep::STATUS_PENDING)
                ->orderBy('level')
                ->lockForUpdate()
                ->first();

            if ($currentStep) {
                $this->assertCanApproveLevel($actor, (int) $currentStep->level);

                $currentStep->update([
                    'status' => ApprovalStep::STATUS_REJECTED,
                    'decided_by' => $actor->id,
                    'comment' => $comment,
                    'decided_at' => now(),
                ]);

                $this->audit->requestEvent(
                    $locked,
                    'APPROVAL_LEVEL_REJECTED',
                    $actor,
                    $previous,
                    LeaveRequest::STATUS_REJECTED,
                    $comment,
                    [
                        'level' => $currentStep->level,
                        'total_levels' => $this->approvalStepCount($locked),
                    ],
                    $httpRequest,
                );
            } elseif ($this->workflowApplies($locked)) {
                $this->assertCanApproveLevel($actor, 1);
            }

            $locked->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'admin_comment' => $comment,
                'version' => $locked->version + 1,
            ]);

            $this->audit->requestEvent($locked, 'REQUEST_REJECTED', $actor, $previous, $locked->status, $comment, [], $httpRequest);

            return $locked->fresh(['employeeProfile.user', 'leaveType', 'approvalSteps.decidedBy']);
        });
    }

    public function initializeSteps(LeaveRequest $leaveRequest, LeaveType|int|null $source = null): void
    {
        $levelCount = $source instanceof LeaveType
            ? $source->approval_level_count
            : $source;

        $levels = $this->normalizedLevelCount($levelCount ?? $leaveRequest->leaveType?->approval_level_count ?? 1);

        for ($level = 1; $level <= $levels; $level++) {
            ApprovalStep::firstOrCreate(
                [
                    'leave_request_id' => $leaveRequest->id,
                    'level' => $level,
                ],
                [
                    'organization_id' => $leaveRequest->organization_id,
                    'status' => ApprovalStep::STATUS_PENDING,
                ],
            );
        }
    }

    public function canApproveLevel(User $actor, int $level): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ($level <= 1) {
            return true;
        }

        return $actor->canManageCompanyRules();
    }

    private function assertCanApproveLevel(User $actor, int $level): void
    {
        if ($this->canApproveLevel($actor, $level)) {
            return;
        }

        throw new InvalidArgumentException($level <= 1
            ? 'Solo una persona responsable puede resolver esta solicitud.'
            : 'Este nivel requiere permiso para gestionar reglas y equipo.');
    }

    private function ensureApprovalSteps(LeaveRequest $leaveRequest): void
    {
        if (! $this->workflowApplies($leaveRequest)) {
            return;
        }

        if ($leaveRequest->approvalSteps()->exists()) {
            return;
        }

        $this->initializeSteps($leaveRequest, $leaveRequest->leaveType);
    }

    private function workflowApplies(LeaveRequest $leaveRequest): bool
    {
        $leaveType = $leaveRequest->leaveType;

        return $leaveType
            && $leaveType->requires_approval
            && ! $leaveType->auto_approve;
    }

    private function approvalStepCount(LeaveRequest $leaveRequest): int
    {
        $count = $leaveRequest->approvalSteps()->count();

        if ($count > 0) {
            return $count;
        }

        return $this->normalizedLevelCount($leaveRequest->leaveType?->approval_level_count ?? 1);
    }

    private function normalizedLevelCount(int|string|null $levelCount): int
    {
        return max(1, min(3, (int) ($levelCount ?: 1)));
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
