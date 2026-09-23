<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;

class PersonPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, Person $person): bool
    {
        return $this->canDeleteRecord($user, $person->record);
    }
}
