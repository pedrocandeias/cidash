<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    /**
     * Audiences are typed as one comma-separated line.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('audiences'))) {
            $this->merge([
                'audiences' => collect(explode(',', $this->input('audiences')))
                    ->map(fn (string $audience) => trim($audience))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $workspaceId = app(WorkspaceContext::class)->get()?->id;

        return [
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'objectives' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'audiences' => ['sometimes', 'nullable', 'array', 'max:20'],
            'audiences.*' => ['string', 'max:100'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'channels' => ['sometimes', 'nullable', 'array'],
            'channels.*' => [Rule::in(ContentItemRequest::CHANNELS)],
            'status' => ['sometimes', Rule::enum(CampaignStatus::class)],
            'responsibles' => ['sometimes', 'array'],
            'responsibles.*' => ['integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
        ];
    }
}
