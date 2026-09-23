import { Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/lib/i18n';
import type { PersonSummary } from './types';
import { profileText } from './types';

export default function CopyProfileButton({
    person,
}: {
    person: PersonSummary;
}) {
    const { t } = useTranslation();
    const [, copy] = useClipboard();

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={async () => {
                if (await copy(profileText(person))) {
                    toast.success(
                        t('Profile copied. Paste it into your email.'),
                    );
                }
            }}
        >
            <Copy />
            {t('Copy to send')}
        </Button>
    );
}
