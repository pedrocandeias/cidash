import { Link } from '@inertiajs/react';
import { ExternalLink, MessageSquare, Paperclip } from 'lucide-react';
import type { MouseEvent } from 'react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useOptions } from '@/lib/options';
import { statusLabels as campaignStatus } from '@/modules/campaigns/types';
import { stageLabels } from '@/modules/content/types';
import { statusLabels as eventStatus } from '@/modules/events/types';
import { statusLabels as pressStatus } from '@/modules/press/types';
import {
    priorityLabels,
    statusLabels as taskStatus,
} from '@/modules/tasks/types';
import { preview } from '@/routes/records';

type Preview = {
    id: string;
    label: string;
    title: string;
    url: string | null;
    status: { value: string; list: string } | null;
    fields: { label: string; value: string; kind: string }[];
    body: string | null;
    tags: string[];
    counts: { comments: number; attachments: number };
};

const statusLists: Record<string, Record<string, string>> = {
    task: taskStatus,
    event: eventStatus,
    press: pressStatus,
    content: stageLabels,
    campaign: campaignStatus,
    triage: {
        new: 'To triage',
        relevant: 'Relevant',
        irrelevant: 'Not relevant',
        archived: 'Archived',
    },
};

const EVENT = 'cidash:preview';

/**
 * Opens the side preview of a record from anywhere (relations, calendar…).
 */
export function previewRecord(id: string) {
    window.dispatchEvent(new CustomEvent(EVENT, { detail: id }));
}

/**
 * A record's title that opens the side preview; Ctrl/Cmd-click still opens its page.
 */
export function RecordLink({
    record,
    className,
}: {
    record: { id: string; title: string; url: string | null };
    className?: string;
}) {
    const open = (event: MouseEvent) => {
        if (
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.button !== 0
        ) {
            return;
        }

        event.preventDefault();
        previewRecord(record.id);
    };

    return record.url ? (
        <Link
            href={record.url}
            onClick={open}
            className={cn('hover:underline', className)}
        >
            {record.title}
        </Link>
    ) : (
        <button
            type="button"
            onClick={open}
            className={cn('text-left hover:underline', className)}
        >
            {record.title}
        </button>
    );
}

/**
 * Side preview of any record, mounted once in the app header.
 */
export function ObjectDrawer() {
    const { t, locale } = useTranslation();
    const eventTypes = useOptions('event_type');
    const formats = useOptions('content_format');
    const taskTypes = useOptions('task_type');
    const [recordId, setRecordId] = useState<string | null>(null);
    const [data, setData] = useState<Preview | null>(null);

    useEffect(() => {
        const open = (event: Event) =>
            setRecordId((event as CustomEvent<string>).detail);
        window.addEventListener(EVENT, open);

        return () => window.removeEventListener(EVENT, open);
    }, []);

    useEffect(() => {
        if (recordId === null) {
            return;
        }

        const controller = new AbortController();
        setData(null);
        fetch(preview.url(recordId), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then(setData)
            .catch(() => {});

        return () => controller.abort();
    }, [recordId]);

    const value = (field: Preview['fields'][number]) => {
        switch (field.kind) {
            case 'date':
                return formatDate(field.value, locale);
            case 'datetime':
                return formatDateTime(field.value, locale);
            case 'priority':
                return t(
                    priorityLabels[
                        field.value as keyof typeof priorityLabels
                    ] ?? field.value,
                );
            case 'event_type':
                return t(eventTypes.label(field.value));
            case 'content_format':
                return t(formats.label(field.value));
            case 'task_type':
                return t(taskTypes.label(field.value));
            default:
                return field.value;
        }
    };

    return (
        <Sheet
            open={recordId !== null}
            onOpenChange={(open) => !open && setRecordId(null)}
        >
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                {data === null ? (
                    <div className="flex h-40 items-center justify-center">
                        <SheetTitle className="sr-only">
                            {t('Loading')}
                        </SheetTitle>
                        <Spinner />
                    </div>
                ) : (
                    <>
                        <SheetHeader>
                            <SheetDescription>{t(data.label)}</SheetDescription>
                            <SheetTitle className="text-lg">
                                {data.title}
                            </SheetTitle>
                        </SheetHeader>
                        <div className="space-y-5 px-4 pb-6 text-sm">
                            {data.status && (
                                <Badge variant="secondary">
                                    {t(
                                        statusLists[data.status.list]?.[
                                            data.status.value
                                        ] ?? data.status.value,
                                    )}
                                </Badge>
                            )}
                            {data.fields.length > 0 && (
                                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
                                    {data.fields.map((field) => (
                                        <div
                                            key={field.label}
                                            className="contents"
                                        >
                                            <dt className="text-muted-foreground">
                                                {t(field.label)}
                                            </dt>
                                            <dd>{value(field)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                            {data.body && (
                                <p className="whitespace-pre-line">
                                    {data.body}
                                </p>
                            )}
                            {data.tags.length > 0 && (
                                <div className="flex flex-wrap gap-1">
                                    {data.tags.map((tag) => (
                                        <Badge key={tag} variant="outline">
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                            <div className="flex gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <MessageSquare className="size-3.5" />
                                    {t(':count comments', {
                                        count: data.counts.comments,
                                    })}
                                </span>
                                <span className="flex items-center gap-1">
                                    <Paperclip className="size-3.5" />
                                    {t(':count attachments', {
                                        count: data.counts.attachments,
                                    })}
                                </span>
                            </div>
                            {data.url && (
                                <Button asChild>
                                    <Link
                                        href={data.url}
                                        onClick={() => setRecordId(null)}
                                    >
                                        <ExternalLink />
                                        {t('Open page')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
