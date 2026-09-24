import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslation } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            {/* The endorsed lockup of the design system. */}
                            <span className="flex items-center gap-3">
                                <AppLogoIcon className="size-8 text-foreground" />
                                <span className="text-[2.35rem] leading-none font-black tracking-[0.04em] text-foreground">
                                    CIDASH
                                </span>
                            </span>
                            <span className="text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                {t(
                                    'University of Porto · Communication and Image',
                                )}
                            </span>
                            <span className="sr-only">{t(title ?? '')}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">
                                {t(title ?? '')}
                            </h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {t(description ?? '')}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
