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

    /**
     * Runs $callback with $workspace as the current workspace, for work that spans teams.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function within(Workspace $workspace, callable $callback): mixed
    {
        $previous = $this->workspace;
        $this->workspace = $workspace;

        try {
            return $callback();
        } finally {
            $this->workspace = $previous;
        }
    }
}
