<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'leave_allowance_id',
        'leave_request_id',
        'movement_type',
        'amount',
        'idempotency_key',
        'reason',
        'created_by',
        'created_at',
    ];

    public function allowance(): BelongsTo
    {
        return $this->belongsTo(LeaveAllowance::class, 'leave_allowance_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
