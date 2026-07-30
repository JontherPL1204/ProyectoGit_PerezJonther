<?php

namespace App\Console\Commands;

use App\Models\NotificationOutbox;
use App\Services\NotificationService;
use Illuminate\Console\Command;

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

        $sender = app(NotificationService::class);

        foreach ($notifications as $notification) {
            $sender->sendNow($notification);
            $notification->refresh();

            if ($notification->status !== 'sent') {
                $this->warn('Notification '.$notification->id.' failed: '.$notification->last_error);
            }
        }

        $this->info('Processed '.$notifications->count().' notification(s).');

        return self::SUCCESS;
    }
}
