import { Link } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, obituaries } from '@/routes/people';

/** People of interest has two sections: the experts and Dead or Alive (obituaries). */
export default function PeopleTabs({
    current,
}: {
    current: 'experts' | 'obituaries';
}) {
    const { t } = useTranslation();
    const tabs = [
        { key: 'experts', label: 'Experts', href: index() },
        { key: 'obituaries', label: 'Dead or Alive', href: obituaries() },
    ] as const;

    return (
        <nav
            className="flex gap-1 border-b"
            aria-label={t('People of interest')}
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm',
                        current === tab.key
                            ? 'border-bronze font-bold text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {t(tab.label)}
                </Link>
            ))}
        </nav>
    );
}
