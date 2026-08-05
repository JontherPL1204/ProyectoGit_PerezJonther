<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class LeaveRequest extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_PENDING_CANCELLATION = 'PENDING_CANCELLATION';

    protected $fillable = [
        'organization_id',
        'employee_profile_id',
        'leave_type_id',
        'unit',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'requested_units',
        'status',
        'user_comment',
        'admin_comment',
        'version',
        'requested_cancel_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'requested_cancel_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function calculationDays(): HasMany
    {
        return $this->hasMany(LeaveRequestCalculationDay::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class);
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('level');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_PENDING_CANCELLATION => 'Cancelacion pendiente',
            default => $this->status,
        };
    }

    public function approvalProgressLabel(): ?string
    {
        if (! Schema::hasTable('approval_steps')) {
            return null;
        }

        $steps = $this->relationLoaded('approvalSteps')
            ? $this->approvalSteps
            : $this->approvalSteps()->get();

        if ($steps->isEmpty()) {
            return null;
        }

        $pending = $steps->firstWhere('status', ApprovalStep::STATUS_PENDING);

        if ($pending) {
            return 'Revision '.$pending->level.' de '.$steps->count();
        }

        return 'Revision completa';
    }
}
