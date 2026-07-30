<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\RequestEvent;
use App\Models\RuleChangeEvent;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    public function requestEvent(
        LeaveRequest $leaveRequest,
        string $action,
        ?User $actor = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $comment = null,
        array $metadata = [],
        ?Request $request = null,
    ): RequestEvent {
        return RequestEvent::create([
            'organization_id' => $leaveRequest->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function ruleChange(
        int $organizationId,
        string $entityType,
        ?int $entityId,
        string $field,
        mixed $previousValue,
        mixed $newValue,
        User $actor,
        ?string $comment = null,
        ?Request $request = null,
    ): RuleChangeEvent {
        return RuleChangeEvent::create([
            'organization_id' => $organizationId,
            'actor_user_id' => $actor->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_name' => $field,
            'previous_value' => $this->stringify($previousValue),
            'new_value' => $this->stringify($newValue),
            'comment' => $comment,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}
