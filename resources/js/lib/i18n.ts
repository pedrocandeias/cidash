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

function intlLocale(locale: string) {
    return locale.replace('_', '-');
}

export function formatDate(value: string, locale: string) {
    // Plain dates (YYYY-MM-DD) are local calendar days, not UTC midnight.
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(`${value}T00:00:00`)
        : new Date(value);

    return new Intl.DateTimeFormat(intlLocale(locale), {
        day: 'numeric',
        month: 'short',
        year:
            date.getFullYear() === new Date().getFullYear()
                ? undefined
                : 'numeric',
    }).format(date);
}

export function formatDateTime(value: string, locale: string) {
    return new Intl.DateTimeFormat(intlLocale(locale), {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

/** Today as YYYY-MM-DD in the browser's local time (not UTC). */
export function localToday() {
    return localDate(new Date());
}

/** A date as YYYY-MM-DD in the browser's local time (not UTC). */
export function localDate(date: Date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
