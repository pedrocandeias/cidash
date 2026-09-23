<?php

namespace Tests\Unit;

use App\Enums\WorkspaceRole;
use PHPUnit\Framework\TestCase;

class WorkspaceRoleTest extends TestCase
{
    public function test_roles_are_cumulative()
    {
        $this->assertTrue(WorkspaceRole::Manager->includes(WorkspaceRole::Editor));
        $this->assertTrue(WorkspaceRole::Manager->includes(WorkspaceRole::Member));
        $this->assertTrue(WorkspaceRole::Editor->includes(WorkspaceRole::Member));
        $this->assertTrue(WorkspaceRole::Editor->includes(WorkspaceRole::Editor));

        $this->assertFalse(WorkspaceRole::Member->includes(WorkspaceRole::Editor));
        $this->assertFalse(WorkspaceRole::Editor->includes(WorkspaceRole::Manager));
    }
}
