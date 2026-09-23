<?php

namespace App\Policies;

use App\Models\PressRequest;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;

class PressRequestPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, PressRequest $pressRequest): bool
    {
        return $this->canDeleteRecord($user, $pressRequest->record);
    }
}
