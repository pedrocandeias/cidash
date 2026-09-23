<?php

namespace App\Http\Controllers\Settings;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TeamMemberRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Invitations;
use App\Support\MailSettings;
use App\Support\TeamMembers;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managers manage the members of their current workspace only.
 */
class TeamController extends Controller
{
    public function __construct(private WorkspaceContext $context, private TeamMembers $members) {}

    public function index(Request $request, MailSettings $mail): Response
    {
        Gate::authorize('manage-members', $this->workspace());

        return Inertia::render('settings/team', [
            'team' => $this->workspace()->name,
            'mailConfigured' => $mail->isConfigured(),
            'members' => $this->workspace()->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roleIn($this->workspace())?->value,
                    'pending' => $user->activated_at === null,
                    'is_me' => $user->is($request->user()),
                ]),
        ]);
    }

    public function store(TeamMemberRequest $request): RedirectResponse
    {
        Gate::authorize('manage-members', $this->workspace());

        $result = $this->members->add(
            $this->workspace(),
            $request->string('email'),
            $request->string('name'),
            WorkspaceRole::from($request->string('role')),
        );

        $this->flashInvitation($result['user'], $result['link'], $result['emailed']);

        return to_route('team.index');
    }

    public function update(Request $request, int $member): RedirectResponse
    {
        Gate::authorize('manage-members', $this->workspace());

        $validated = $request->validate(['role' => ['required', Rule::enum(WorkspaceRole::class)]]);

        $this->members->changeRole($this->workspace(), $this->member($member), WorkspaceRole::from($validated['role']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('team.index');
    }

    public function destroy(int $member): RedirectResponse
    {
        Gate::authorize('manage-members', $this->workspace());

        $this->members->remove($this->workspace(), $this->member($member));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed from the team.')]);

        return to_route('team.index');
    }

    public function resendInvitation(int $member, Invitations $invitations): RedirectResponse
    {
        Gate::authorize('manage-members', $this->workspace());

        $user = $this->member($member);
        abort_if($user->activated_at !== null, 404);

        $result = $invitations->send($user, $this->workspace());
        $this->flashInvitation($user, $result['link'], $result['emailed']);

        return to_route('team.index');
    }

    /**
     * Resolved here, not in the constructor: controllers are built before route middleware runs.
     */
    private function workspace(): Workspace
    {
        return $this->context->get() ?? abort(403);
    }

    /**
     * Only members of the current workspace can be acted upon.
     */
    private function member(int $id): User
    {
        return $this->workspace()->members()->whereKey($id)->firstOrFail();
    }

    private function flashInvitation(User $user, ?string $link, bool $emailed): void
    {
        $message = match (true) {
            $link === null => __(':name was added to the team.', ['name' => $user->name]),
            $emailed => __('Invitation sent to :email.', ['email' => $user->email]),
            default => __('Invitation created. Copy the link and send it to :email.', ['email' => $user->email]),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        if ($link !== null && ! $emailed) {
            Inertia::flash('invitationLink', $link);
        }
    }
}
