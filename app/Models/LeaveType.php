<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'organization_id',
        'department_id',
        'code',
        'name',
        'unit',
        'consumes_balance',
        'balance_code',
        'requires_approval',
        'auto_approve',
        'allow_half_day',
        'attachment_requirement',
        'is_medical',
        'notice_value',
        'notice_unit',
        'min_units',
        'max_units',
        'monthly_limit_units',
        'yearly_limit_units',
        'approval_level_count',
        'allow_retroactive',
        'visible_to_employees',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'consumes_balance' => 'boolean',
            'requires_approval' => 'boolean',
            'auto_approve' => 'boolean',
            'allow_half_day' => 'boolean',
            'is_medical' => 'boolean',
            'allow_retroactive' => 'boolean',
            'visible_to_employees' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
