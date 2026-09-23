<?php

namespace App\Enums;

/**
 * Roles are cumulative: member < editor < manager (ARCHITECTURE.md §2.6).
 * The super admin is not a workspace role (see User::$is_super_admin).
 */
enum WorkspaceRole: string
{
    case Member = 'member';
    case Editor = 'editor';
    case Manager = 'manager';

    public function includes(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Member => 1,
            self::Editor => 2,
            self::Manager => 3,
        };
    }
}
