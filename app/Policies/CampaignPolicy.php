<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;

class CampaignPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->canDeleteRecord($user, $campaign->record);
    }
}
