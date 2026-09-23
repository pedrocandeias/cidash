<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\EmailPreferences;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Notifications: what each user also receives by email.
 */
class NotificationPreferencesController extends Controller
{
    public function edit(Request $request, MailSettings $mail): Response
    {
        return Inertia::render('settings/notifications', [
            'preferences' => EmailPreferences::of($request->user()),
            'emailConfigured' => $mail->isConfigured(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_map(fn () => ['sometimes', 'boolean'], EmailPreferences::DEFAULTS));
        $user = $request->user();

        $user->forceFill(['email_preferences' => array_merge(EmailPreferences::of($user), array_map('boolval', $validated))])->save();

        return back();
    }
}
