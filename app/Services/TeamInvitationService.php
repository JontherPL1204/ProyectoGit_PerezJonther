<?php

namespace App\Services;

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TeamInvitationService
{
    /**
     * @return array{token:string,url:string}
     */
    public function rotateToken(TeamInvitation $invitation): array
    {
        $token = TeamInvitation::newPlainToken();
        $invitation->forceFill([
            'token_hash' => TeamInvitation::tokenHash($token),
        ])->save();

        return [
            'token' => $token,
            'url' => route('invitations.show', $token),
        ];
    }

    public function send(TeamInvitation $invitation, string $url): void
    {
        try {
            Mail::send([
                'html' => 'emails.invitation',
                'text' => 'emails.invitation-text',
            ], [
                'appName' => config('app.name', 'N-Woffu Prime'),
                'organization' => $invitation->organization,
                'invitation' => $invitation,
                'inviteUrl' => $url,
            ], function ($message) use ($invitation): void {
                $message->to($invitation->email)
                    ->subject('[N-Woffu Prime] Invitacion para unirte al equipo');
            });

            $invitation->update([
                'last_sent_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $invitation->update([
                'last_error' => $exception->getMessage(),
            ]);
        }
    }
}
