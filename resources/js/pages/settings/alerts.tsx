import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import type { AlertSeverity } from '@/modules/alerts/types';
import { severityLabels } from '@/modules/alerts/types';
import { index, update } from '@/routes/alert-rules';

type Rule = {
    id: number;
    message: string;
    description: string;
    params: Record<string, number>;
    severity: AlertSeverity;
    active: boolean;
};

const paramLabels: Record<string, string> = {
    hours: 'Hours',
    days: 'Days',
    days_before: 'Days before',
    failures: 'Failures',
    factor: 'Times the usual',
    minimum: 'Minimum',
};

export default function AlertRules({ rules }: { rules: Rule[] }) {
    const { t } = useTranslation();
    const save = (
        rule: Rule,
        data: Record<string, boolean | string | Record<string, number>>,
    ) => router.patch(update(rule.id).url, data, { preserveScroll: true });

    return (
        <>
            <Head title={t('Alerts')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Alert rules')}
                    description={t(
                        'Managers and the people responsible are notified when an alert opens.',
                    )}
                />

                <ul className="divide-y rounded-lg border">
                    {rules.map((rule) => (
                        <li key={rule.id} className="space-y-3 p-4">
                            <label className="flex items-center gap-2 text-sm font-medium">
                                <Checkbox
                                    checked={rule.active}
                                    onCheckedChange={(checked) =>
                                        save(rule, { active: checked === true })
                                    }
                                />
                                {t(rule.message)}
                            </label>
                            <p className="text-sm text-muted-foreground">
                                {t(rule.description, rule.params)}
                            </p>
                            <div className="flex flex-wrap items-center gap-4 text-sm">
                                {Object.entries(rule.params).map(
                                    ([name, value]) => (
                                        <label
                                            key={name}
                                            className="flex items-center gap-2"
                                        >
                                            {t(paramLabels[name] ?? name)}
                                            <Input
                                                type="number"
                                                min={1}
                                                max={720}
                                                defaultValue={value}
                                                className="h-8 w-20"
                                                onBlur={(event) => {
                                                    const next = Number(
                                                        event.target.value,
                                                    );

                                                    if (
                                                        next >= 1 &&
                                                        next !== value
                                                    ) {
                                                        save(rule, {
                                                            params: {
                                                                [name]: next,
                                                            },
                                                        });
                                                    }
                                                }}
                                            />
                                        </label>
                                    ),
                                )}
                                <Select
                                    value={rule.severity}
                                    onValueChange={(severity) =>
                                        save(rule, { severity })
                                    }
                                >
                                    <SelectTrigger
                                        className="h-8 w-36"
                                        aria-label={t('Severity')}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(
                                            Object.keys(
                                                severityLabels,
                                            ) as AlertSeverity[]
                                        ).map((severity) => (
                                            <SelectItem
                                                key={severity}
                                                value={severity}
                                            >
                                                {t(severityLabels[severity])}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

AlertRules.layout = {
    breadcrumbs: [{ title: 'Alerts', href: index() }],
};
