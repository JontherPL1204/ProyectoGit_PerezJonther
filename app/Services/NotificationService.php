<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\NotificationOutbox;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    private const IMMEDIATE_DELIVERY_EVENTS = [
        'REQUEST_APPROVED',
        'REQUEST_REJECTED',
        'CANCELLATION_ACCEPTED',
        'CANCELLATION_REJECTED',
    ];

    public function requestCreated(LeaveRequest $leaveRequest): void
    {
        $admins = User::where('organization_id', $leaveRequest->organization_id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            $this->enqueue($leaveRequest, 'REQUEST_CREATED', $admin->email);
        }
    }

    public function cancellationRequested(LeaveRequest $leaveRequest): void
    {
        $admins = User::where('organization_id', $leaveRequest->organization_id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            $this->enqueue($leaveRequest, 'CANCELLATION_REQUESTED', $admin->email);
        }
    }

    public function requestResolved(LeaveRequest $leaveRequest, string $event): void
    {
        $email = $leaveRequest->employeeProfile?->user?->email;

        if ($email) {
            $this->enqueue($leaveRequest, $event, $email);
        }
    }

    private function enqueue(LeaveRequest $leaveRequest, string $event, string $recipientEmail): void
    {
        $recipientType = $event === 'REQUEST_CREATED' || $event === 'CANCELLATION_REQUESTED'
            ? 'admin'
            : 'user';

        $rule = NotificationRule::where('organization_id', $leaveRequest->organization_id)
            ->where('event', $event)
            ->where('recipient_type', $recipientType)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return;
        }

        $notification = NotificationOutbox::create([
            'organization_id' => $leaveRequest->organization_id,
            'leave_request_id' => $leaveRequest->id,
            'event' => $event,
            'recipient_email' => $recipientEmail,
            'subject' => $this->render($rule->subject_template, $leaveRequest),
            'body' => $this->render($rule->body_template, $leaveRequest),
            'status' => 'pending',
            'available_at' => now(),
        ]);

        if (in_array($event, self::IMMEDIATE_DELIVERY_EVENTS, true)) {
            $this->sendNow($notification);
        }
    }

    public function sendNow(NotificationOutbox $notification): void
    {
        try {
            $notification->loadMissing([
                'leaveRequest.employeeProfile.user',
                'leaveRequest.leaveType',
            ]);

            $data = $this->emailData($notification);

            Mail::send([
                'html' => 'emails.notification',
                'text' => 'emails.notification-text',
            ], $data, function ($message) use ($notification): void {
                $message->to($notification->recipient_email)
                    ->subject($notification->subject);
            });

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'attempts' => $notification->attempts + 1,
                'available_at' => null,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $notification->update([
                'status' => $notification->attempts >= 2 ? 'failed' : 'pending',
                'attempts' => $notification->attempts + 1,
                'available_at' => now()->addMinutes(10),
                'last_error' => $exception->getMessage(),
            ]);
        }
    }

    private function render(string $template, LeaveRequest $leaveRequest): string
    {
        return strtr($template, [
            '{{employee}}' => $leaveRequest->employeeProfile?->user?->name ?? 'Empleado',
            '{{type}}' => $leaveRequest->leaveType?->name ?? 'Ausencia',
            '{{start_date}}' => $leaveRequest->start_date?->format('d/m/Y') ?? '',
            '{{end_date}}' => $leaveRequest->end_date?->format('d/m/Y') ?? '',
            '{{duration}}' => $this->durationLabel($leaveRequest),
            '{{request_id}}' => (string) $leaveRequest->id,
            '{{status}}' => $leaveRequest->statusLabel(),
            '{{request_url}}' => route('leave-requests.show', $leaveRequest),
            '{{app_url}}' => config('app.url'),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function emailData(NotificationOutbox $notification): array
    {
        $leaveRequest = $notification->leaveRequest;
        $presentation = $this->eventPresentation($notification->event);
        $requestUrl = $leaveRequest ? route('leave-requests.show', $leaveRequest) : config('app.url');

        return [
            'appName' => config('app.name', 'N-Woffu Prime'),
            'appUrl' => config('app.url'),
            'notification' => $notification,
            'leaveRequest' => $leaveRequest,
            'presentation' => $presentation,
            'messageBody' => $notification->body,
            'requestUrl' => $requestUrl,
            'details' => $leaveRequest ? $this->requestDetails($leaveRequest) : [],
            'comments' => $leaveRequest ? $this->requestComments($leaveRequest) : [],
            'securityNote' => 'Por seguridad, los documentos medicos no se adjuntan al correo. Revisa cualquier justificante dentro de la aplicacion.',
        ];
    }

    /**
     * @return array<string,string>
     */
    private function eventPresentation(string $event): array
    {
        return match ($event) {
            'REQUEST_CREATED' => [
                'eyebrow' => 'Solicitud pendiente',
                'headline' => 'Nueva solicitud para revisar',
                'badge' => 'Pendiente',
                'badgeBackground' => '#fff2ce',
                'badgeColor' => '#6f4d00',
                'accent' => '#f0b74b',
                'cta' => 'Revisar solicitud',
            ],
            'REQUEST_APPROVED' => [
                'eyebrow' => 'Solicitud aprobada',
                'headline' => 'Tu solicitud fue aprobada',
                'badge' => 'Aprobada',
                'badgeBackground' => '#e8ffc9',
                'badgeColor' => '#234900',
                'accent' => '#b8ff4d',
                'cta' => 'Ver detalle',
            ],
            'REQUEST_REJECTED' => [
                'eyebrow' => 'Solicitud rechazada',
                'headline' => 'Tu solicitud fue rechazada',
                'badge' => 'Rechazada',
                'badgeBackground' => '#ffe3e1',
                'badgeColor' => '#8f1f1b',
                'accent' => '#e25050',
                'cta' => 'Ver motivo',
            ],
            'CANCELLATION_REQUESTED' => [
                'eyebrow' => 'Cancelacion pendiente',
                'headline' => 'Hay una cancelacion por revisar',
                'badge' => 'Cancelacion',
                'badgeBackground' => '#e6efff',
                'badgeColor' => '#163f86',
                'accent' => '#4b88f0',
                'cta' => 'Revisar cancelacion',
            ],
            'CANCELLATION_ACCEPTED' => [
                'eyebrow' => 'Cancelacion aceptada',
                'headline' => 'La cancelacion fue aceptada',
                'badge' => 'Cancelada',
                'badgeBackground' => '#e8ffc9',
                'badgeColor' => '#234900',
                'accent' => '#b8ff4d',
                'cta' => 'Ver detalle',
            ],
            'CANCELLATION_REJECTED' => [
                'eyebrow' => 'Cancelacion rechazada',
                'headline' => 'La cancelacion fue rechazada',
                'badge' => 'Sigue aprobada',
                'badgeBackground' => '#ffe3e1',
                'badgeColor' => '#8f1f1b',
                'accent' => '#e25050',
                'cta' => 'Ver detalle',
            ],
            default => [
                'eyebrow' => 'Notificacion',
                'headline' => 'Actualizacion de solicitud',
                'badge' => 'Actualizacion',
                'badgeBackground' => '#f5f6f2',
                'badgeColor' => '#111318',
                'accent' => '#b8ff4d',
                'cta' => 'Abrir aplicacion',
            ],
        };
    }

    /**
     * @return array<string,string>
     */
    private function requestDetails(LeaveRequest $leaveRequest): array
    {
        return [
            'Solicitud' => '#'.$leaveRequest->id,
            'Empleado' => $leaveRequest->employeeProfile?->user?->name ?? 'Empleado',
            'Tipo' => $leaveRequest->leaveType?->name ?? 'Ausencia',
            'Periodo' => ($leaveRequest->start_date?->format('d/m/Y') ?? '').' - '.($leaveRequest->end_date?->format('d/m/Y') ?? ''),
            'Duracion' => $this->durationLabel($leaveRequest),
            'Estado' => $leaveRequest->statusLabel(),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function requestComments(LeaveRequest $leaveRequest): array
    {
        return array_filter([
            'Comentario del empleado' => $leaveRequest->user_comment,
            'Comentario de revision' => $leaveRequest->admin_comment,
        ], fn (?string $comment): bool => filled($comment));
    }

    private function durationLabel(LeaveRequest $leaveRequest): string
    {
        $unit = $leaveRequest->unit === 'DAYS'
            ? ($leaveRequest->requested_units === 1 ? 'dia' : 'dias')
            : 'minutos';

        return $leaveRequest->requested_units.' '.$unit;
    }
}
