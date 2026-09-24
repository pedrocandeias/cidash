<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Support\Assignments;
use App\Support\Options;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for creating and for partial updates (e.g. only the status).
 */
class TaskRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $workspaceId = app(WorkspaceContext::class)->get()?->id;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', Rule::in(app(Options::class)->keys('task_type'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'source_id' => [$creating ? 'nullable' : 'prohibited', 'uuid'],
            // Only members of the current workspace can be assigned.
            ...Assignments::rules(withCo: true),
        ];
    }
}
