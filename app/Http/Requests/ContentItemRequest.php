<?php

namespace App\Http\Requests;

use App\Enums\ContentStage;
use App\Support\Assignments;
use App\Support\Options;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentItemRequest extends FormRequest
{
    /**
     * Distribution channels offered in the form.
     */
    public const CHANNELS = ['website', 'newsletter', 'instagram', 'facebook', 'linkedin', 'x', 'youtube', 'tiktok', 'press', 'outreach', 'print', 'screens'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $workspaceId = app(WorkspaceContext::class)->get()?->id;

        return [
            'title' => [$required, 'string', 'max:255'],
            'brief' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'format' => [$required, Rule::in(app(Options::class)->keys('content_format'))],
            'channels' => ['sometimes', 'nullable', 'array'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'stage' => ['sometimes', Rule::enum(ContentStage::class)],
            ...Assignments::rules(),
            'due_at' => ['sometimes', 'nullable', 'date'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'published_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
