<?php

namespace App\Http\Controllers\Settings;

use App\Core\Terms;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceOption;
use App\Support\Options;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Types: the team's event types and content formats (managers).
 */
class OptionController extends Controller
{
    public function index(WorkspaceContext $context, Options $options): Response
    {
        Gate::authorize('manage-members', $context->get() ?? abort(403));

        return Inertia::render('settings/types', [
            'lists' => collect(array_keys(Options::DEFAULTS))->mapWithKeys(fn (string $list) => [
                $list => $options->all($list)->map(fn (WorkspaceOption $option) => $option->only('id', 'key', 'label', 'color', 'active'))->values(),
            ]),
        ]);
    }

    public function store(Request $request, WorkspaceContext $context, Options $options): RedirectResponse
    {
        Gate::authorize('manage-members', $context->get() ?? abort(403));
        $validated = $this->validated($request, null);

        WorkspaceOption::create([
            ...$validated,
            'key' => $options->newKey($validated['list'], $validated['label']),
            'position' => $options->all($validated['list'])->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, WorkspaceContext $context, WorkspaceOption $option): RedirectResponse
    {
        Gate::authorize('manage-members', $context->get() ?? abort(403));

        // The key never changes: records store it.
        $option->update($this->validated($request, $option));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WorkspaceOption $option): array
    {
        $rules = [
            'label' => [$option ? 'sometimes' : 'required', 'string', 'max:60'],
            'color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active' => ['sometimes', 'boolean'],
        ];
        if ($option === null) {
            $rules['list'] = ['required', Rule::in(array_keys(Options::DEFAULTS))];
        }

        return validator($request->all(), $rules)
            ->after(function (Validator $validator) use ($request, $option) {
                if (! $request->filled('label')) {
                    return;
                }

                // "Vídeo" and "video" are the same entry, and so are a default ("News") and its translation ("Notícia").
                $label = Terms::normalize((string) $request->input('label'));
                $duplicate = WorkspaceOption::where('list', $option->list ?? $request->input('list'))
                    ->when($option, fn ($query) => $query->whereKeyNot($option->id))
                    ->get()
                    ->contains(fn (WorkspaceOption $other) => in_array($label, [$other->normalized_label, Terms::normalize(__($other->label))], true));
                if ($duplicate) {
                    $validator->errors()->add('label', __('This name already exists.'));
                }
            })
            ->validate();
    }
}
