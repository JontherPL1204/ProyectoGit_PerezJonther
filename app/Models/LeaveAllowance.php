<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveAllowance extends Model
{
    protected $fillable = [
        'organization_id',
        'employee_profile_id',
        'leave_type_id',
        'balance_code',
        'period_start',
        'period_end',
        'assigned_units',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
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

    public function movements(): HasMany
    {
        return $this->hasMany(LeaveBalanceMovement::class);
    }

    public function balance(): int
    {
        return (int) $this->movements()->sum('amount');
    }
}
