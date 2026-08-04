<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'department_id',
        'location_id',
        'employee_code',
        'hired_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(LeaveAllowance::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeScheduleAssignment::class);
    }

    public function calendarAssignments(): HasMany
    {
        return $this->hasMany(EmployeeCalendarAssignment::class);
    }

    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPosition::class)
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }
}
