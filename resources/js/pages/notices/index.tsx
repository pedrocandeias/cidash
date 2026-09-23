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
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import NoticeFields, {
    noticeFormTransform,
} from '@/modules/notices/notice-fields';
import type { Notice } from '@/modules/notices/notice-fields';
import NoticeMeta from '@/modules/notices/notice-meta';
import { index, show, store } from '@/routes/notices';

type Props = {
    notices: Notice[];
    view: 'active' | 'archive';
    can: { pin: boolean };
};

export default function Notices({ notices, view, can }: Props) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('Notices')} />

            <div className="max-w-3xl space-y-6 px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={t('Notices')} />
                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button>{t('New notice')}</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-2xl">
                            <DialogTitle>{t('New notice')}</DialogTitle>
                            <Form
                                {...store.form()}
                                transform={noticeFormTransform}
                                onSuccess={() => setCreating(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <NoticeFields
                                            errors={errors}
                                            canPin={can.pin}
                                        />
                                        <Button disabled={processing}>
                                            {t('Publish')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <nav className="flex gap-1" aria-label={t('Notices')}>
                    {(['active', 'archive'] as const).map((option) => (
                        <Link
                            key={option}
                            href={index({
                                query:
                                    option === 'archive'
                                        ? { view: 'archive' }
                                        : {},
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                view === option
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(
                                option === 'active'
                                    ? 'Current'
                                    : 'Scheduled and expired',
                            )}
                        </Link>
                    ))}
                </nav>

                {notices.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No notices.')}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {notices.map((notice) => (
                            <li
                                key={notice.id}
                                className={cn(
                                    'space-y-2 rounded-lg border p-4',
                                    notice.pinned &&
                                        'border-foreground/30 bg-muted/40',
                                )}
                            >
                                <NoticeMeta notice={notice} />
                                <Link
                                    href={show(notice.id)}
                                    className="block font-medium hover:underline"
                                >
                                    {notice.title}
                                </Link>
                                <p className="line-clamp-3 text-sm whitespace-pre-line text-muted-foreground">
                                    {notice.body}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Notices.layout = {
    breadcrumbs: [{ title: 'Notices', href: index() }],
};
