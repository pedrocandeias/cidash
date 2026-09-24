<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Core\RecordPage;
use App\Enums\Priority;
use App\Enums\RelationType;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Record;
use App\Models\Task;
use App\Models\User;
use App\Support\Assignments;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $view = $request->query('view') === 'team' ? 'team' : 'mine';
        $status = in_array($request->query('status'), ['done', 'all'], true) ? $request->query('status') : 'open';
        $layout = $request->query('layout') === 'board' ? 'board' : 'list';

        $tasks = Task::query()
            ->with('assignees:id,name')
            ->when($view === 'mine', fn ($query) => $query->assignedTo($request->user()->id))
            ->when($status === 'open', fn ($query) => $query->whereIn('status', TaskStatus::open()))
            ->when($status === 'done', fn ($query) => $query->where('status', TaskStatus::Done))
            // Nearest deadline first, tasks without deadline last.
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->latest()
            ->get()
            ->map(fn (Task $task) => $this->summary($task));

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'filters' => ['view' => $view, 'status' => $status, 'layout' => $layout],
            'members' => $this->members(),
        ]);
    }

    public function store(TaskRequest $request, Links $links): RedirectResponse
    {
        $source = $request->filled('source_id') ? Record::findOrFail((string) $request->input('source_id')) : null;

        $task = DB::transaction(function () use ($request, $source, $links) {
            $task = Task::create([
                ...$request->safe()->except(['source_id', 'assignees']),
                'priority' => $request->input('priority', Priority::Normal->value),
                'status' => $request->input('status', TaskStatus::Todo->value),
                'source_object_id' => $source?->id,
            ]);

            if ($source !== null) {
                $links->link($task, $source, RelationType::OriginatedFrom);
            }

            return $task;
        });

        Assignments::sync($task, $request->input('assignees', []), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    public function show(Request $request, Task $task, RecordPage $page): Response
    {
        $task->load(['assignees:id,name', 'source', 'record']);

        return Inertia::render('tasks/show', [
            'task' => [
                ...$this->summary($task),
                'description' => $task->description,
                'created_at' => $task->record->created_at?->toIso8601String(),
                'source' => $task->source ? ['type' => $task->source->type, 'title' => $task->source->title] : null,
            ],
            'members' => $this->members(),
            ...$page->for($task->record, $request->user()),
            'can' => ['delete' => $request->user()->can('delete', $task)],
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->safe()->except('assignees'));

        if ($request->has('assignees')) {
            Assignments::sync($task, $request->input('assignees', []), $request->user());
        }

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return to_route('tasks.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'deadline' => $task->deadline?->toDateString(),
            'assignees' => Assignments::present($task),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function members(): array
    {
        $workspace = $this->context->get() ?? abort(403);

        return $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
