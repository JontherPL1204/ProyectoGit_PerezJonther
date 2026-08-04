<?php

namespace App\Models;

use App\Support\NotificationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    protected $fillable = [
        'organization_id',
        'leave_request_id',
        'event',
        'recipient_email',
        'subject',
        'body',
        'status',
        'attempts',
        'available_at',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function statusLabel(): string
    {
        return NotificationLabels::status($this->status);
    }

    public function eventLabel(): string
    {
        return NotificationLabels::event($this->event);
    }
}
