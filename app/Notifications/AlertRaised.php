<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Notifications\Notification;

class AlertRaised extends Notification
{
    public function __construct(private Alert $alert) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->alert->message,
            'title' => $this->alert->title,
            'by' => null,
            'workspace_id' => $this->alert->workspace_id,
            'url' => route('alerts.index', absolute: false),
        ];
    }
}
