<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
