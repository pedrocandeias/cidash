<?php

namespace App\Alerts;

use Carbon\CarbonInterface;

/**
 * One problem found by a rule. The key identifies it across evaluations, so the
 * same problem is alerted once and resolved when it is no longer found.
 */
final readonly class Finding
{
    /**
     * @param  string|null  $recordId  the record the alert is about
     * @param  array<int, int>  $owners  users responsible for it, notified with the managers
     */
    public function __construct(
        public string $key,
        public string $title,
        public ?string $recordId = null,
        public ?CarbonInterface $dueAt = null,
        public array $owners = [],
    ) {}
}
