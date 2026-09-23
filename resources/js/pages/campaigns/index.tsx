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
import { formatDate, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import CampaignFields, {
    campaignFormTransform,
} from '@/modules/campaigns/campaign-fields';
import type { CampaignSummary } from '@/modules/campaigns/types';
import { statusLabels } from '@/modules/campaigns/types';
import type { Member } from '@/modules/tasks/types';
import { index, show, store } from '@/routes/campaigns';

type Props = {
    campaigns: CampaignSummary[];
    view: 'current' | 'all';
    members: Member[];
};

export default function Campaigns({ campaigns, view, members }: Props) {
    const { t, locale } = useTranslation();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('Campaigns')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={t('Campaigns')} />
                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button>{t('New campaign')}</Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogTitle>{t('New campaign')}</DialogTitle>
                            <Form
                                {...store.form()}
                                transform={campaignFormTransform}
                                onSuccess={() => setCreating(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <CampaignFields
                                            members={members}
                                            errors={errors}
                                        />
                                        <Button disabled={processing}>
                                            {t('Create campaign')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <nav className="flex gap-1" aria-label={t('Campaigns')}>
                    {(['current', 'all'] as const).map((option) => (
                        <Link
                            key={option}
                            href={index({
                                query: option === 'all' ? { view: 'all' } : {},
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                view === option
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(
                                option === 'current'
                                    ? 'Planned and active'
                                    : 'Everything',
                            )}
                        </Link>
                    ))}
                </nav>

                {campaigns.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No campaigns here.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {campaigns.map((campaign) => (
                            <li
                                key={campaign.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                <div className="min-w-48 flex-1">
                                    <Link
                                        href={show(campaign.id)}
                                        className="text-sm font-medium hover:underline"
                                    >
                                        {campaign.name}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {campaign.responsibles
                                            .map((user) => user.name)
                                            .join(', ') || t('Nobody')}
                                    </p>
                                </div>
                                <span className="text-xs text-muted-foreground">
                                    {t(statusLabels[campaign.status])}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {t(':count items', {
                                        count: campaign.items ?? 0,
                                    })}
                                </span>
                                <span className="w-40 text-right text-sm text-muted-foreground">
                                    {campaign.start_date &&
                                        formatDate(campaign.start_date, locale)}
                                    {campaign.end_date &&
                                        ` – ${formatDate(campaign.end_date, locale)}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Campaigns.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: index() }],
};
