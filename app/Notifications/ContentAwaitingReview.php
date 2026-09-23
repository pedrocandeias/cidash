<?php

namespace App\Notifications;

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ContentAwaitingReview extends Notification
{
    public function __construct(
        private ContentItem $item,
        private ?User $by,
    ) {}

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
            'message' => 'Content awaiting review',
            'title' => $this->item->title,
            'by' => $this->by?->name,
            'workspace_id' => $this->item->record->workspace_id,
            'url' => route('content.show', $this->item, absolute: false),
        ];
    }
}
