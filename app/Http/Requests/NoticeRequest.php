<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoticeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string', 'max:20000'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:published_at'],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
