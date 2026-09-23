<?php

namespace App\Providers;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\MailSettings;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class);

        $this->app->resolving('mail.manager', function () {
            try {
                app(MailSettings::class)->apply();
            } catch (QueryException) {
                // Not migrated yet: keep the .env mailer.
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Permissions that are not tied to a single model (ARCHITECTURE.md §2.6).
     */
    protected function configureGates(): void
    {
        Gate::define('super-admin', fn (User $user) => $user->is_super_admin);

        Gate::define('manage-members', fn (User $user, Workspace $workspace) => $user->hasRole($workspace, WorkspaceRole::Manager));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
