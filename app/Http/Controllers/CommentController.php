<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Comment;
use App\Models\Record;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comments on any record of the current workspace.
 */
class CommentController extends Controller
{
    public function store(Request $request, Record $record): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $record->comments()->create([...$validated, 'user_id' => $request->user()->id]);

        return back();
    }

    public function destroy(Request $request, Comment $comment, WorkspaceContext $context): RedirectResponse
    {
        $user = $request->user();
        $workspace = $context->get() ?? abort(403);

        abort_unless($comment->user_id === $user->id || $user->hasRole($workspace, WorkspaceRole::Manager), 403);

        $comment->delete();

        return back();
    }
}
