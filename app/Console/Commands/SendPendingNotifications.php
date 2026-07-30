<?php

namespace App\Console\Commands;

use App\Models\NotificationOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPendingNotifications extends Command
{
    protected $signature = 'nwoffu:send-notifications {--limit=25}';

    protected $description = 'Send pending N-Woffu Prime email notifications from the outbox.';

    public function handle(): int
    {
        $notifications = NotificationOutbox::where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->oldest()
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($notifications as $notification) {
            try {
                Mail::raw($notification->body, function ($message) use ($notification): void {
                    $message->to($notification->recipient_email)
                        ->subject($notification->subject);
                });

                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempts' => $notification->attempts + 1,
                    'last_error' => null,
                ]);
            } catch (Throwable $exception) {
                $notification->update([
                    'status' => $notification->attempts >= 2 ? 'failed' : 'pending',
                    'attempts' => $notification->attempts + 1,
                    'available_at' => now()->addMinutes(10),
                    'last_error' => $exception->getMessage(),
                ]);

                $this->warn('Notification '.$notification->id.' failed: '.$exception->getMessage());
            }
        }

        $this->info('Processed '.$notifications->count().' notification(s).');

        return self::SUCCESS;
    }
}
