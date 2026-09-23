<?php

namespace App\Http\Controllers;

use App\Calendar\Ics;
use App\Enums\EventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Calendar subscription for Outlook, Google Calendar or a phone: a secret URL
 * with the events of the user's teams, or only those the user is responsible for.
 */
class CalendarSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Creating a new link revokes the previous one.
        $request->user()->forceFill(['calendar_token' => Str::random(48)])->save();

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['calendar_token' => null])->save();

        return back();
    }

    public function feed(Request $request, string $token, WorkspaceContext $context): Response
    {
        $user = User::where('calendar_token', $token)->whereNull('deactivated_at')->first() ?? abort(404);
        $mine = $request->boolean('mine');

        $events = $user->workspaces()->get()->flatMap(fn (Workspace $workspace) => $context->within($workspace, fn () => CalendarEvent::query()
            ->where('start_at', '>=', now()->subMonths(3))
            ->where('start_at', '<', now()->addYear())
            ->where('status', '!=', EventStatus::Cancelled)
            ->when($mine, fn ($query) => $query->where('responsible_user_id', $user->id))
            ->orderBy('start_at')
            ->get()));

        return response(Ics::calendar($events, $mine ? 'CIDASH · '.__('My events') : 'CIDASH'), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    /**
     * One event, to add to a personal calendar.
     */
    public function event(CalendarEvent $event): Response
    {
        return response(Ics::calendar([$event], 'CIDASH'), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($event->title).'.ics"',
        ]);
    }
}
