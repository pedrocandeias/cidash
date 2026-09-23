<?php

namespace App\Console\Commands;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Invitations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('cidash:create-user {email} {name}
    {--super-admin : Grant super admin}
    {--workspace= : Workspace slug to add the user to}
    {--role=member : Role in that workspace (member, editor, manager)}')]
#[Description('Create a user and send (or print) an invitation to set the password (there is no public registration)')]
class CreateUser extends Command
{
    public function handle(Invitations $invitations): int
    {
        $email = Str::lower($this->argument('email'));
        $role = WorkspaceRole::tryFrom($this->option('role'));

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        if ($role === null) {
            $this->error('Invalid role. Use member, editor or manager.');

            return self::FAILURE;
        }

        $workspace = null;
        if ($slug = $this->option('workspace')) {
            $workspace = Workspace::where('slug', $slug)->first();

            if ($workspace === null) {
                $this->error("Workspace [{$slug}] not found.");

                return self::FAILURE;
            }
        }

        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $email,
            // Unusable until the user sets their own password through the link below.
            'password' => Str::password(64),
        ]);
        $user->forceFill(['is_super_admin' => (bool) $this->option('super-admin')])->save();

        $workspace?->members()->attach($user, ['role' => $role]);

        $invitation = $invitations->send($user, $workspace);

        $this->info("User created: {$email}");
        $this->line($invitation['emailed'] ? 'Invitation emailed. Link (valid for 7 days):' : 'Invitation link (valid for 7 days):');
        $this->line($invitation['link']);

        return self::SUCCESS;
    }
}
