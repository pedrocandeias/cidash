<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Invitation links let a new user set their password (the "invites" broker,
 * valid for 7 days). They are emailed when email is configured; otherwise, or if
 * sending fails, the link is returned so it can be shared by hand.
 */
class Invitations
{
    public function __construct(private MailSettings $mail) {}

    /**
     * @return array{link: string, emailed: bool}
     */
    public function send(User $user, ?Workspace $workspace = null): array
    {
        $token = Password::broker('invites')->createToken($user);
        $link = route('invitation.show', ['token' => $token, 'email' => $user->email]);

        $emailed = false;

        if ($this->mail->isConfigured()) {
            try {
                $user->notify(new InvitationNotification($link, $workspace->name ?? config('app.name')));
                $emailed = true;
            } catch (Throwable $e) {
                Log::warning('Invitation email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return ['link' => $link, 'emailed' => $emailed];
    }
}
