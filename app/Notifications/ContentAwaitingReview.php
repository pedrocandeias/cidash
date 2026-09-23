<?php

namespace App\Notifications;

use App\Models\ContentItem;
use App\Models\User;

class ContentAwaitingReview extends CidashNotification
{
    public function __construct(ContentItem $item, ?User $by)
    {
        parent::__construct([
            'message' => 'Content awaiting review',
            'title' => $item->title,
            'by' => $by?->name,
            'workspace_id' => $item->record->workspace_id,
            'url' => route('content.show', $item, absolute: false),
        ]);
    }

    public function kind(): string
    {
        return 'reviews';
    }
}
