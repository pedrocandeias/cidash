<?php

namespace App\Http\Controllers;

use App\Alerts\Evaluator;
use App\Briefing\DailyBriefing;
use App\Briefing\WeeklyBriefing;
use App\Models\Briefing;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    /**
     * Archive of the team's briefings.
     */
    public function index(): Response
    {
        return Inertia::render('briefings/index', [
            'briefings' => Briefing::orderByDesc('period_start')->limit(120)->get()->map(fn (Briefing $briefing) => [
                'id' => $briefing->id,
                'kind' => $briefing->kind,
                'date' => $briefing->period_start->toDateString(),
                'counts' => collect($briefing->content)->mapWithKeys(fn (array $section) => [$section['key'] => $section['count']]),
            ]),
        ]);
    }

    /**
     * Today's briefing, generated on first view when the scheduler has not (weekends, new teams).
     */
    public function today(WorkspaceContext $context, DailyBriefing $daily): RedirectResponse
    {
        $briefing = Briefing::where('kind', 'daily')->where('period_start', today())->first()
            ?? $daily->generate($context->get() ?? abort(403));

        return to_route('briefings.show', $briefing);
    }

    /**
     * This week's briefing, generated on first view like today's.
     */
    public function week(WorkspaceContext $context, WeeklyBriefing $weekly): RedirectResponse
    {
        $briefing = Briefing::where('kind', 'weekly')->where('period_start', now()->startOfWeek())->first()
            ?? $weekly->generate($context->get() ?? abort(403));

        return to_route('briefings.show', $briefing);
    }

    public function show(Briefing $briefing): Response
    {
        return Inertia::render('briefings/show', [
            'briefing' => [
                'id' => $briefing->id,
                'kind' => $briefing->kind,
                'date' => $briefing->period_start->toDateString(),
                'generated_at' => $briefing->generated_at->toIso8601String(),
                'sections' => $briefing->content,
                'is_current' => $this->isCurrent($briefing),
            ],
            'previous' => Briefing::where('kind', $briefing->kind)->where('period_start', '<', $briefing->period_start)->orderByDesc('period_start')->value('id'),
            'next' => Briefing::where('kind', $briefing->kind)->where('period_start', '>', $briefing->period_start)->orderBy('period_start')->value('id'),
        ]);
    }

    /**
     * Brings the current briefing up to date; past briefings stay as they were.
     */
    public function refresh(Briefing $briefing, WorkspaceContext $context, DailyBriefing $daily, WeeklyBriefing $weekly, Evaluator $evaluator): RedirectResponse
    {
        abort_unless($this->isCurrent($briefing), 403);
        $workspace = $context->get() ?? abort(403);
        $evaluator->run($workspace);
        ($briefing->kind === 'weekly' ? $weekly : $daily)->generate($workspace);

        return back();
    }

    /**
     * Today's daily or this week's weekly briefing: the only ones that can still change.
     */
    private function isCurrent(Briefing $briefing): bool
    {
        return $briefing->kind === 'weekly'
            ? $briefing->period_start->isSameDay(now()->startOfWeek())
            : $briefing->period_start->isToday();
    }
}
