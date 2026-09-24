import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { formatDate, useTranslation } from '@/lib/i18n';
import { channelLabels } from '@/modules/content/types';
import type { CampaignDetails } from './types';
import { statusLabels } from './types';

function Field({ label, children }: { label: string; children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <div className="space-y-1">
            <dt className="text-xs font-bold tracking-wide text-muted-foreground uppercase">
                {t(label)}
            </dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

/**
 * The campaign's details, read-only; "Edit" on the page switches to the form.
 */
export default function CampaignSummaryView({
    campaign,
}: {
    campaign: CampaignDetails;
}) {
    const { t, locale } = useTranslation();
    const dates = [campaign.start_date, campaign.end_date]
        .map((date) => (date ? formatDate(date, locale) : null))
        .filter(Boolean)
        .join(' – ');

    return (
        <div className="-mt-6 space-y-5">
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field label="Status">
                    <Badge variant="secondary">
                        {t(statusLabels[campaign.status])}
                    </Badge>
                </Field>
                <Field label="Dates">{dates || '—'}</Field>
                <Field label="People responsible">
                    {campaign.responsibles.length > 0
                        ? campaign.responsibles
                              .map((person) => person.name)
                              .join(', ')
                        : '—'}
                </Field>
                <Field label="Channels">
                    {campaign.channels.length > 0
                        ? campaign.channels
                              .map((channel) =>
                                  t(channelLabels[channel] ?? channel),
                              )
                              .join(', ')
                        : '—'}
                </Field>
            </dl>

            {campaign.audiences.length > 0 && (
                <Field label="Audiences">
                    <span className="flex flex-wrap gap-1">
                        {campaign.audiences.map((audience) => (
                            <Badge key={audience} variant="outline">
                                {audience}
                            </Badge>
                        ))}
                    </span>
                </Field>
            )}
            {campaign.description && (
                <Field label="Description">
                    <p className="max-w-prose whitespace-pre-line">
                        {campaign.description}
                    </p>
                </Field>
            )}
            {campaign.objectives && (
                <Field label="Objectives">
                    <p className="max-w-prose whitespace-pre-line">
                        {campaign.objectives}
                    </p>
                </Field>
            )}
        </div>
    );
}
