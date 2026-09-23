<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accepting an invitation = setting the first password through the "invites" broker.
 */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $activated = null;

        $status = Password::broker('invites')->reset($credentials, function (User $user, string $password) use (&$activated) {
            $user->forceFill([
                'password' => $password,
                'activated_at' => $user->activated_at ?? now(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $activated = $user;
        });

        if ($activated === null) {
            return back()->withErrors(['email' => __($status)]);
        }

        Auth::login($activated);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
