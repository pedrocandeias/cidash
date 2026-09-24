import { usePage } from '@inertiajs/react';

export type OptionList = 'event_type' | 'content_format' | 'asset_category';

const fallbackColor = '#6b7280';

/**
 * The current team's event types or content formats (Settings → Types).
 * Labels are English keys for the defaults and plain text for the team's own;
 * pass them through t() either way.
 */
export function useOptions(list: OptionList) {
    const { options } = usePage().props;
    const items = options?.[list] ?? [];

    return {
        items,
        active: items.filter((option) => option.active),
        label: (key: string) =>
            items.find((option) => option.key === key)?.label ?? key,
        color: (key: string) =>
            items.find((option) => option.key === key)?.color ?? fallbackColor,
    };
}
