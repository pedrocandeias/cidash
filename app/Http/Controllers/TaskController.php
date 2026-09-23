<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Enums\Priority;
use App\Enums\RelationType;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Record;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
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

        $tasks = Task::query()
            ->with('assignee:id,name')
            ->when($view === 'mine', fn ($query) => $query->where('assigned_to', $request->user()->id))
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
            'filters' => ['view' => $view, 'status' => $status],
            'members' => $this->members(),
        ]);
    }

    public function store(TaskRequest $request, Links $links): RedirectResponse
    {
        $source = $request->filled('source_id') ? Record::findOrFail((string) $request->input('source_id')) : null;

        $task = DB::transaction(function () use ($request, $source, $links) {
            $task = Task::create([
                ...$request->safe()->except('source_id'),
                'priority' => $request->input('priority', Priority::Normal->value),
                'status' => $request->input('status', TaskStatus::Todo->value),
                'source_object_id' => $source?->id,
            ]);

            if ($source !== null) {
                $links->link($task, $source, RelationType::OriginatedFrom);
            }

            return $task;
        });

        $this->notifyAssignee($task, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    public function show(Request $request, Task $task): Response
    {
        $task->load(['assignee:id,name', 'source', 'record']);

        return Inertia::render('tasks/show', [
            'task' => [
                ...$this->summary($task),
                'description' => $task->description,
                'created_at' => $task->record->created_at?->toIso8601String(),
                'source' => $task->source ? ['type' => $task->source->type, 'title' => $task->source->title] : null,
            ],
            'members' => $this->members(),
            'comments' => $task->record->comments()->with('user:id,name')->oldest()->get()
                ->map(fn (Comment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->user?->name,
                    'created_at' => $comment->created_at->toIso8601String(),
                    'can_delete' => $comment->user_id === $request->user()->id,
                ]),
            'activity' => $task->record->activity()->with('user:id,name')->latest('id')->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'fields' => array_keys($activity->changes ?? []),
                    'user' => $activity->user?->name,
                    'created_at' => $activity->created_at->toIso8601String(),
                ]),
            'recordId' => $task->id,
            'can' => ['delete' => $request->user()->can('delete', $task)],
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $previousAssignee = $task->assigned_to;

        $task->update($request->validated());

        if ($task->assigned_to !== $previousAssignee) {
            $this->notifyAssignee($task, $request->user());
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
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
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

    private function notifyAssignee(Task $task, User $actor): void
    {
        if ($task->assignee !== null && ! $task->assignee->is($actor)) {
            $task->assignee->notify(new TaskAssigned($task, $actor));
        }
    }
}
