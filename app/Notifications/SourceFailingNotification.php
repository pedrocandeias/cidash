<?php

namespace App\Notifications;

use App\Models\Source;
use Illuminate\Notifications\Notification;

/**
 * For super admins, who maintain the sources catalogue.
 */
class SourceFailingNotification extends Notification
{
    public function __construct(private Source $source) {}

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
            'message' => 'Source failing',
            'title' => $this->source->name,
            'by' => null,
            'workspace_id' => null,
            'url' => route('admin.sources.index', absolute: false),
        ];
    }
}
