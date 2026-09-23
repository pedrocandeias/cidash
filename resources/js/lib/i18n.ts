import { usePage } from '@inertiajs/react';

/**
 * UI strings are written in English in the code and translated through
 * lang/{locale}.json, the same files Laravel's __() reads. Missing keys fall
 * back to the English text. Placeholders use Laravel's `:name` syntax.
 */
export function useTranslation() {
    const { translations, locale } = usePage().props;

    const t = (key: string, replace: Record<string, string | number> = {}) =>
        Object.entries(replace).reduce(
            (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
            translations[key] ?? key,
        );

    return { t, locale };
}
