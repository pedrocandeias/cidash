<?php

namespace App\Http\Controllers;

use App\Models\Record;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Personal reminders on any record of the current workspace.
 */
class ReminderController extends Controller
{
    public function store(Request $request, Record $record): RedirectResponse
    {
        $validated = $request->validate(['remind_at' => ['required', 'date', 'after:now']]);

        Reminder::create([
            'object_id' => $record->id,
            'user_id' => $request->user()->id,
            // The browser sends UTC; times are stored in the app time zone.
            'remind_at' => CarbonImmutable::parse($validated['remind_at'])->setTimezone(config('app.timezone')),
        ]);

        return back();
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $reminder->delete();

        return back();
    }
}
