<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialNetworkState;
use App\Social\Networks\Network;
use App\Social\SocialCollector;
use App\Support\SocialSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Administration → Social networks (super admin): credentials and state of each network.
 */
class SocialNetworkController extends Controller
{
    public function edit(SocialSettings $settings, SocialCollector $collector): Response
    {
        return Inertia::render('admin/social', [
            'networks' => collect($collector->networks())->map(function (Network $network, string $key) {
                $state = SocialNetworkState::find($key);

                return [
                    'key' => $key,
                    'label' => $network->label(),
                    'configured' => $network->configured(),
                    'interval' => $network->interval(),
                    'last_fetched_at' => $state?->last_fetched_at?->toIso8601String(),
                    'last_error' => $state?->last_error,
                ];
            })->values(),
            'settings' => $settings->forForm(),
            'tags' => $collector->followedTags(),
        ]);
    }

    public function update(Request $request, string $network, SocialSettings $settings): RedirectResponse
    {
        abort_unless(array_key_exists($network, SocialSettings::FIELDS), 404);

        $fields = [...SocialSettings::FIELDS[$network], ...SocialSettings::SECRETS[$network]];
        $validated = $request->validate([
            ...collect($fields)->mapWithKeys(fn (string $field) => [$field => ['nullable', 'string', 'max:2000']])->all(),
            'instance' => ['nullable', 'url:https', 'max:255'],
            'clear' => ['sometimes', 'boolean'],
        ]);

        $settings->save($network, $validated, $request->boolean('clear'));
        SocialNetworkState::whereKey($network)->update(['last_error' => null, 'consecutive_failures' => 0]);

        return back();
    }

    /**
     * Tries the network with one hashtag, without filing anything.
     */
    public function test(Request $request, string $network, SocialCollector $collector): RedirectResponse
    {
        $validated = $request->validate(['tag' => ['required', 'string', 'max:100', Rule::notIn([''])]]);
        $instance = $collector->networks()[$network] ?? abort(404);

        try {
            $count = count($instance->fetch(ltrim($validated['tag'], '#')));
            Inertia::flash('toast', ['type' => 'success', 'message' => __(':network: :count posts found.', ['network' => $instance->label(), 'count' => $count])]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => (string) preg_replace('/\?\S*/', '?…', $e->getMessage())]);
        }

        return back();
    }
}
