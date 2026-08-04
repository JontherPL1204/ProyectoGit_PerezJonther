<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'leave_request_id',
        'actor_user_id',
        'action',
        'previous_status',
        'new_status',
        'comment',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'REQUEST_CREATED' => 'Solicitud creada',
            'REQUEST_APPROVED' => 'Solicitud aprobada',
            'REQUEST_REJECTED' => 'Solicitud rechazada',
            'REQUEST_CANCELLED' => 'Solicitud cancelada',
            'CANCELLATION_REQUESTED' => 'Cancelacion solicitada',
            'CANCELLATION_ACCEPTED' => 'Cancelacion aceptada',
            'CANCELLATION_REJECTED' => 'Cancelacion rechazada',
            'ATTACHMENT_VIEWED' => 'Adjunto descargado',
            'ATTACHMENT_PREVIEWED' => 'Adjunto previsualizado',
            'ATTACHMENT_REVIEWED' => 'Justificante validado',
            default => 'Actividad registrada',
        };
    }
}
