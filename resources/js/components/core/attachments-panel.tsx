import { router } from '@inertiajs/react';
import { Paperclip, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/attachments';

export type AttachmentItem = {
    id: number;
    name: string;
    size: number;
    author: string | null;
    created_at: string;
    url: string;
    can_delete: boolean;
};

function humanSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function AttachmentsPanel({
    recordId,
    attachments,
}: {
    recordId: string;
    attachments: AttachmentItem[];
}) {
    const { t, locale } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        setUploading(true);
        router.post(
            store(recordId).url,
            { files: Array.from(files) },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? t('Something went wrong.'),
                    ),
                onFinish: () => {
                    setUploading(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between">
                <h2 className="text-base font-medium">{t('Attachments')}</h2>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={uploading}
                    onClick={() => input.current?.click()}
                >
                    <Paperclip />
                    {t(uploading ? 'Uploading…' : 'Attach')}
                </Button>
                <input
                    ref={input}
                    type="file"
                    multiple
                    className="hidden"
                    onChange={(event) => upload(event.target.files)}
                />
            </div>

            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No attachments.')}
                </p>
            ) : (
                <ul className="space-y-2 text-sm">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex items-start gap-2"
                        >
                            <div className="min-w-0 flex-1">
                                <a
                                    href={attachment.url}
                                    target="_blank"
                                    rel="noopener"
                                    className="block truncate font-medium hover:underline"
                                >
                                    {attachment.name}
                                </a>
                                <span className="text-xs text-muted-foreground">
                                    {humanSize(attachment.size)}
                                    {attachment.author &&
                                        ` · ${attachment.author}`}
                                    {` · ${formatDateTime(attachment.created_at, locale)}`}
                                </span>
                            </div>
                            {attachment.can_delete && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label={t('Remove attachment')}
                                    onClick={() =>
                                        router.delete(
                                            destroy(attachment.id).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
