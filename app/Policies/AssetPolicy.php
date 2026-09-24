<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;

class AssetPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, Asset $asset): bool
    {
        return $this->canDeleteRecord($user, $asset->record);
    }
}
