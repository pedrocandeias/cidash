import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Credits, year and version, at the bottom of every page.
 */
export function AppFooter({ className }: { className?: string }) {
    const { t } = useTranslation();
    const { version } = usePage().props;

    return (
        <footer
            className={cn(
                'flex flex-wrap gap-x-2 gap-y-1 px-4 py-4 text-xs text-muted-foreground',
                className,
            )}
        >
            <span>
                © {new Date().getFullYear()}{' '}
                {t('University of Porto · Communication and Image Office')}
            </span>
            <span aria-hidden>·</span>
            <span>
                CIDASH{version ? ` ${t('version :version', { version })}` : ''}
            </span>
        </footer>
    );
}
