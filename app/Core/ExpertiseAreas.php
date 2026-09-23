<?php

namespace App\Core;

use App\Models\ExpertiseArea;
use App\Models\Person;

/**
 * Expertise areas of the current workspace (the "Pessoas de interesse" dropdown).
 */
class ExpertiseAreas extends Vocabulary
{
    protected function model(): string
    {
        return ExpertiseArea::class;
    }

    protected function pivotTable(): string
    {
        return 'expertise_area_person';
    }

    protected function termKey(): string
    {
        return 'expertise_area_id';
    }

    protected function ownerKey(): string
    {
        return 'person_id';
    }

    /**
     * @param  array<int, string>  $names
     */
    public function sync(Person $person, array $names): void
    {
        $person->areas()->sync($this->idsFor($names));
    }
}
