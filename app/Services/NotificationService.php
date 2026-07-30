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
            Mail::raw($notification->body, function ($message) use ($notification): void {
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
            '{{status}}' => $leaveRequest->statusLabel(),
            '{{app_url}}' => config('app.url'),
        ]);
    }
}
