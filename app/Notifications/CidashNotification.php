<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\EmailPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An in-app notification (the bell) that also goes by email when the user wants it.
 * Only the email is queued. The notification keeps plain data, never models:
 * workspace-scoped models cannot be restored by the queue worker.
 */
abstract class CidashNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{message: string, title: string, by: string|null, workspace_id: int|null, url: string|null}  $data
     */
    public function __construct(protected array $data) {}

    /**
     * The preference that controls the email (see EmailPreferences::DEFAULTS).
     */
    abstract public function kind(): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && app(EmailPreferences::class)->wants($notifiable, $this->kind())
            ? ['database', 'mail']
            : ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__($this->data['message']).': '.$this->data['title'])
            ->line(__($this->data['message']).':')
            ->line('**'.$this->data['title'].'**');

        if ($this->data['by'] !== null) {
            $message->line(__('By :name', ['name' => $this->data['by']]));
        }
        if ($this->data['url'] !== null) {
            $message->action(__('Open in CIDASH'), url($this->data['url']));
        }

        return $message->line(__('You can choose what you receive by email in Settings → Notifications.'));
    }
}
