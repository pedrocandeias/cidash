import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import PressFields, { pressFormTransform } from '@/modules/press/press-fields';
import type { Known, PressSummary } from '@/modules/press/types';
import { statusLabels, urgency } from '@/modules/press/types';
import type { Member } from '@/modules/tasks/types';
import { index, show, store } from '@/routes/press';

type Props = {
    requests: PressSummary[];
    status: 'open' | 'closed' | 'all';
    members: Member[];
    known: Known;
};

const lights = {
    red: 'bg-critical',
    amber: 'bg-warning',
};

export default function PressRequests({
    requests,
    status,
    members,
    known,
}: Props) {
    const { t, locale } = useTranslation();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('Press requests')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={t('Press requests')} />
                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button>{t('New request')}</Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                            <DialogTitle>{t('New request')}</DialogTitle>
                            <Form
                                {...store.form()}
                                transform={pressFormTransform}
                                onSuccess={() => setCreating(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <PressFields
                                            members={members}
                                            known={known}
                                            errors={errors}
                                        />
                                        <Button disabled={processing}>
                                            {t('Register request')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <nav className="flex gap-1" aria-label={t('Status')}>
                    {(['open', 'closed', 'all'] as const).map((option) => (
                        <Link
                            key={option}
                            href={index({
                                query:
                                    option === 'open' ? {} : { status: option },
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                status === option
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(
                                {
                                    open: 'Ongoing',
                                    closed: 'Finished',
                                    all: 'Everything',
                                }[option],
                            )}
                        </Link>
                    ))}
                </nav>

                {requests.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No press requests here.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {requests.map((request) => {
                            const light = urgency(request);

                            return (
                                <li
                                    key={request.id}
                                    className="flex flex-wrap items-center gap-3 px-4 py-3"
                                >
                                    <span
                                        className={cn(
                                            'size-2.5 shrink-0 rounded-xs',
                                            light
                                                ? lights[light]
                                                : 'bg-transparent',
                                        )}
                                        aria-label={
                                            light === 'red'
                                                ? t('Deadline within 24 hours')
                                                : light === 'amber'
                                                  ? t('Deadline within 3 days')
                                                  : undefined
                                        }
                                    />
                                    <div className="min-w-48 flex-1">
                                        <Link
                                            href={show(request.id)}
                                            className="text-sm font-medium hover:underline"
                                        >
                                            {request.subject}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                request.media_outlet,
                                                request.journalist,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {t(statusLabels[request.status])}
                                    </span>
                                    <span className="w-32 truncate text-sm text-muted-foreground">
                                        {request.responsible ?? t('Nobody')}
                                    </span>
                                    <span
                                        className={cn(
                                            'w-28 text-right text-sm',
                                            light === 'red'
                                                ? 'font-medium text-critical'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {request.deadline &&
                                            formatDateTime(
                                                request.deadline,
                                                locale,
                                            )}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

PressRequests.layout = {
    breadcrumbs: [{ title: 'Press requests', href: index() }],
};
