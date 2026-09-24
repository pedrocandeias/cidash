import { Check, Monitor, Moon, Palette, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/lib/i18n';

const options: { value: Appearance; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'colour', label: 'Colour', icon: Palette },
    { value: 'system', label: 'System', icon: Monitor },
];

/** Light or dark screen, one click away in the header (also in Settings → Appearance). */
export function AppearanceToggle() {
    const { t } = useTranslation();
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const Icon =
        appearance === 'colour'
            ? Palette
            : resolvedAppearance === 'dark'
              ? Moon
              : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('Screen colours')}
                    title={t('Screen colours')}
                >
                    <Icon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onSelect={() => updateAppearance(option.value)}
                    >
                        <option.icon />
                        <span className="flex-1">{t(option.label)}</span>
                        {appearance === option.value && (
                            <Check className="size-4" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
