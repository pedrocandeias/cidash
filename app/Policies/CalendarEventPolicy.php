<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;

class CalendarEventPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->canDeleteRecord($user, $event->record);
    }
}
