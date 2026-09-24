<?php

namespace App\Support;

use App\Models\WorkspaceOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The current team's configurable lists. Each team starts with the defaults
 * below (English labels, translated in the UI) and can rename, recolour, add
 * or switch entries off. Entries are never deleted: records keep their key.
 * Workflow states stay fixed, because approvals, alerts and briefings use them.
 */
class Options
{
    /** @var array<string, array<string, array{0: string, 1: string|null}>> list => key => [label, colour] */
    public const DEFAULTS = [
        'event_type' => [
            'institutional' => ['Institutional', '#2563eb'],
            'campaign' => ['Campaign', '#7c3aed'],
            'publication' => ['Publication', '#047857'],
            'ephemeris' => ['Ephemeris', '#b45309'],
            'deadline' => ['Deadline', '#dc2626'],
        ],
        'asset_category' => [
            'photos' => ['Photos', null],
            'videos' => ['Videos', null],
            'logos' => ['Logos', null],
            'graphics' => ['Graphics', null],
            'illustrations' => ['Illustrations', null],
        ],
        'content_format' => [
            'news' => ['News', null],
            'article' => ['Article', null],
            'press_release' => ['Press release', null],
            'social_post' => ['Social media post', null],
            'video' => ['Video', null],
            'photos' => ['Photos', null],
            'newsletter' => ['Newsletter', null],
            'podcast' => ['Podcast', null],
            'other' => ['Other', null],
        ],
    ];

    /**
     * @return Collection<int, WorkspaceOption>
     */
    public function all(string $list): Collection
    {
        $options = WorkspaceOption::where('list', $list)->orderBy('position')->orderBy('id')->get();

        if ($options->isEmpty()) {
            $position = 0;
            foreach (self::DEFAULTS[$list] as $key => [$label, $color]) {
                WorkspaceOption::create(['list' => $list, 'key' => $key, 'label' => $label, 'color' => $color, 'position' => $position++]);
            }

            return $this->all($list);
        }

        return $options;
    }

    /**
     * Every key of the list, switched off or not, since existing records may use it.
     *
     * @return array<int, string>
     */
    public function keys(string $list): array
    {
        return $this->all($list)->pluck('key')->all();
    }

    /**
     * @return array<string, array<int, array{key: string, label: string, color: string|null, active: bool}>>
     */
    public function forFrontend(): array
    {
        return collect(array_keys(self::DEFAULTS))->mapWithKeys(fn (string $list) => [
            $list => $this->all($list)->map(fn (WorkspaceOption $option) => [
                'key' => $option->key,
                'label' => $option->label,
                'color' => $option->color,
                'active' => $option->active,
            ])->all(),
        ])->all();
    }

    /**
     * A stable key for a new entry, unique within the list.
     */
    public function newKey(string $list, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'option';
        $keys = $this->keys($list);
        $key = $base;
        for ($n = 2; in_array($key, $keys, true); $n++) {
            $key = "{$base}_{$n}";
        }

        return $key;
    }
}
