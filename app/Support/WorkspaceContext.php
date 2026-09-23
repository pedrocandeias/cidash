<?php

namespace App\Support;

use App\Models\Workspace;

/**
 * The workspace the current request works in. Bound per request (scoped) and set
 * by the EnsureWorkspace middleware; the core workspace scope reads it.
 */
class WorkspaceContext
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }
}
