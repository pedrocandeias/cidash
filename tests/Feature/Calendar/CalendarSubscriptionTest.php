<?php

namespace Tests\Feature\Calendar;

use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function event(Workspace $workspace, array $attributes): CalendarEvent
    {
        app(WorkspaceContext::class)->set($workspace);

        return CalendarEvent::create([...['type' => 'institutional', 'priority' => 'normal', 'status' => 'confirmed', 'start_at' => now()->addDay()->setTime(10, 0)], ...$attributes]);
    }

    public function test_the_feed_lists_the_events_of_the_users_teams_only()
    {
        $reitoria = Workspace::factory()->create();
        $feup = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($reitoria, WorkspaceRole::Member)->create();
        $feup->members()->attach($user, ['role' => WorkspaceRole::Member]);
        $this->event($reitoria, ['title' => 'Dia Aberto, Reitoria; manhã', 'location' => 'Salão Nobre'])->syncAssignees([$user->id]);
        $this->event($feup, ['title' => 'Conferência FEUP']);
        $this->event($other, ['title' => 'De outra equipa']);
        $this->event($reitoria, ['title' => 'Cancelado', 'status' => 'cancelled']);
        $this->event($reitoria, ['title' => 'Feriado', 'all_day' => true, 'start_at' => now()->addDays(5)->startOfDay(), 'end_at' => now()->addDays(6)->startOfDay()]);

        $this->actingAs($user)->post(route('calendar.subscription.store'))->assertRedirect();
        $token = $user->refresh()->calendar_token;
        $this->assertNotNull($token);
        $this->actingAs($user)->get(route('events.index'))
            ->assertInertia(fn (Assert $page) => $page->where('subscriptionUrl', route('calendar.feed', $token))->missing('auth.user.calendar_token'));

        auth()->logout();
        $ics = $this->get(route('calendar.feed', $token))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Dia Aberto\, Reitoria\; manhã', $ics);
        $this->assertStringContainsString('LOCATION:Salão Nobre', $ics);
        $this->assertStringContainsString('SUMMARY:Conferência FEUP', $ics);
        $this->assertStringNotContainsString('De outra equipa', $ics);
        $this->assertStringNotContainsString('Cancelado', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:'.now()->addDays(5)->format('Ymd'), $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:'.now()->addDays(7)->format('Ymd'), $ics, 'all-day ends are exclusive');
        $this->assertStringContainsString('DTSTART:'.now()->addDay()->setTime(10, 0)->utc()->format('Ymd\THis\Z'), $ics);

        $mine = $this->get(route('calendar.feed', $token).'?mine=1')->getContent();
        $this->assertStringContainsString('Dia Aberto', $mine);
        $this->assertStringNotContainsString('Conferência FEUP', $mine);
    }

    public function test_a_new_link_or_revoking_stops_the_old_one()
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace, WorkspaceRole::Member)->create();

        $this->actingAs($user)->post(route('calendar.subscription.store'));
        $old = $user->refresh()->calendar_token;
        $this->actingAs($user)->post(route('calendar.subscription.store'));
        $this->get(route('calendar.feed', $old))->assertNotFound();
        $this->get(route('calendar.feed', $user->refresh()->calendar_token))->assertOk();

        $this->actingAs($user)->delete(route('calendar.subscription.destroy'));
        $this->assertNull($user->refresh()->calendar_token);

        $this->get(route('calendar.feed', 'nothing'))->assertNotFound();
    }

    public function test_long_lines_are_folded_and_one_event_can_be_downloaded()
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace, WorkspaceRole::Member)->create();
        $event = $this->event($workspace, ['title' => str_repeat('Conferência ', 12)]);

        $response = $this->actingAs($user)->get(route('events.ics', $event->id))->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        foreach (explode("\r\n", $response->getContent()) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
        $this->assertStringContainsString("\r\n ", $response->getContent());
    }
}
