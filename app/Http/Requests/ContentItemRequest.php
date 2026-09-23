<?php

namespace App\Http\Requests;

use App\Enums\ContentFormat;
use App\Enums\ContentStage;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentItemRequest extends FormRequest
{
    /**
     * Distribution channels offered in the form.
     */
    public const CHANNELS = ['website', 'newsletter', 'instagram', 'facebook', 'linkedin', 'x', 'youtube', 'tiktok', 'press', 'print', 'screens'];

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
            'format' => [$required, Rule::enum(ContentFormat::class)],
            'channels' => ['sometimes', 'nullable', 'array'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'stage' => ['sometimes', Rule::enum(ContentStage::class)],
            'owner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'published_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
