<?php

namespace App\Models;

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
        return match ($this->status) {
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            default => 'Pendiente',
        };
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'REQUEST_CREATED' => 'Nueva solicitud',
            'REQUEST_APPROVED' => 'Solicitud aprobada',
            'REQUEST_REJECTED' => 'Solicitud rechazada',
            'CANCELLATION_REQUESTED' => 'Cancelacion solicitada',
            'CANCELLATION_ACCEPTED' => 'Cancelacion aceptada',
            'CANCELLATION_REJECTED' => 'Cancelacion rechazada',
            default => $this->event,
        };
    }
}
