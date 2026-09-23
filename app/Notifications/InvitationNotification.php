<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(
        private string $url,
        private string $workspaceName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Invitation to CIDASH'))
            ->line(__('You have been added to the :team team in CIDASH.', ['team' => $this->workspaceName]))
            ->line(__('Set your password to start using your account.'))
            ->action(__('Accept invitation'), $this->url)
            ->line(__('This link expires in :days days.', ['days' => 7]));
    }
}
