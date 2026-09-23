import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/lib/i18n';

/**
 * Shown when an invitation could not be emailed: the link to send by hand.
 */
export default function InvitationLink({ link }: { link: string }) {
    const { t } = useTranslation();
    const [copied, copy] = useClipboard();

    return (
        <div className="space-y-2 rounded-lg border p-4">
            <p className="text-sm font-medium">{t('Invitation link')}</p>
            <p className="text-sm text-muted-foreground">
                {t(
                    'Send this link to the person. It is valid for 7 days and can only be used once.',
                )}
            </p>
            <div className="flex gap-2">
                <Input
                    value={link}
                    readOnly
                    onFocus={(e) => e.target.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => copy(link)}
                    aria-label={t('Copy link')}
                >
                    {copied === link ? <Check /> : <Copy />}
                </Button>
            </div>
        </div>
    );
}
