<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailSettingsRequest;
use App\Mail\TestMail;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingsController extends Controller
{
    public function edit(MailSettings $settings): Response
    {
        return Inertia::render('admin/email', [
            'settings' => $settings->forForm(),
        ]);
    }

    public function update(MailSettingsRequest $request, MailSettings $settings): RedirectResponse
    {
        /** @var array{host: string, port: int, encryption: string, username: ?string, password: ?string, from_address: string, from_name: string} $data */
        $data = $request->validated();
        $data['port'] = (int) $data['port'];

        $settings->save($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email settings saved.')]);

        return to_route('admin.email.edit');
    }

    /**
     * Send a test email to the current user with the saved settings.
     */
    public function test(Request $request, MailSettings $settings): RedirectResponse
    {
        if (! $settings->isConfigured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Save the email settings first.')]);

            return to_route('admin.email.edit');
        }

        $email = $request->user()->email;

        try {
            Mail::to($email)->send(new TestMail);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The test email could not be sent: :error', ['error' => $e->getMessage()])]);

            return to_route('admin.email.edit');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent to :email.', ['email' => $email])]);

        return to_route('admin.email.edit');
    }
}
