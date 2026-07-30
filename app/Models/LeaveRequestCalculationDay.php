<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestCalculationDay extends Model
{
    protected $fillable = [
        'leave_request_id',
        'work_date',
        'is_working_day',
        'is_holiday',
        'computed_units',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_working_day' => 'boolean',
            'is_holiday' => 'boolean',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
