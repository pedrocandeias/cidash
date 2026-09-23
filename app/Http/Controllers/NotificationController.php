<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark as read and open what the notification is about, switching to its workspace.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        $workspaceId = $notification->data['workspace_id'] ?? null;
        if ($workspaceId !== null && $workspaceId !== $user->current_workspace_id) {
            $user->forceFill(['current_workspace_id' => $workspaceId])->save();
        }

        return redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
