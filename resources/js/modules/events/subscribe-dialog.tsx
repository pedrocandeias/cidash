import { router } from '@inertiajs/react';
import { Copy, Rss } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/calendar/subscription';

function CopyField({
    id,
    label,
    url,
}: {
    id: string;
    label: string;
    url: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{t(label)}</Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    readOnly
                    value={url}
                    onFocus={(event) => event.target.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={t('Copy')}
                    onClick={() =>
                        navigator.clipboard
                            .writeText(url)
                            .then(() => toast.success(t('Link copied.')))
                    }
                >
                    <Copy />
                </Button>
            </div>
        </div>
    );
}

export default function SubscribeDialog({ url }: { url: string | null }) {
    const { t } = useTranslation();
    const options = { preserveScroll: true, preserveState: true };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Rss />
                    {t('Subscribe')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{t('Subscribe to the calendar')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'Add this link to Outlook, Google Calendar or your phone to see the events of your teams there. It updates by itself. Anyone with the link can see the events, so keep it to yourself.',
                    )}
                </DialogDescription>

                {url === null ? (
                    <Button
                        className="justify-self-start"
                        onClick={() => router.post(store().url, {}, options)}
                    >
                        {t('Create my link')}
                    </Button>
                ) : (
                    <div className="space-y-4">
                        <CopyField
                            id="subscription-all"
                            label="All events"
                            url={url}
                        />
                        <CopyField
                            id="subscription-mine"
                            label="Only events I am responsible for"
                            url={`${url}?mine=1`}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(store().url, {}, options)
                                }
                            >
                                {t('Create a new link')}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.delete(destroy().url, options)
                                }
                            >
                                {t('Revoke link')}
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t('A new link stops the old one from working.')}
                        </p>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
