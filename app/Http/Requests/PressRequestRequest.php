<?php

namespace App\Http\Requests;

use App\Enums\PressRequestStatus;
use App\Support\Assignments;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PressRequestRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $workspaceId = app(WorkspaceContext::class)->get()?->id;

        return [
            'subject' => [$required, 'string', 'max:255'],
            'request' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'journalist' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media_outlet' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'received_at' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            ...Assignments::rules(),
            'status' => ['sometimes', Rule::enum(PressRequestStatus::class)],
            'response_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
