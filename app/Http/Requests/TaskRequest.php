<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\TaskStatus;
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
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            // Only members of the current workspace can be assigned.
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'source_id' => [$creating ? 'nullable' : 'prohibited', 'uuid'],
        ];
    }
}
