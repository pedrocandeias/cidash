<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Priority;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEventRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $workspaceId = app(WorkspaceContext::class)->get()?->id;

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'type' => [$required, Rule::enum(EventType::class)],
            'start_at' => [$required, 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_at'],
            'all_day' => ['sometimes', 'boolean'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'organizer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'responsible_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'status' => ['sometimes', Rule::enum(EventStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
