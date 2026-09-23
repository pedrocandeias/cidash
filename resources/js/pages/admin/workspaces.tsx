import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InvitationLink from '@/components/core/invitation-link';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/workspaces';
import * as managers from '@/routes/admin/workspaces/managers';
import { switchMethod } from '@/routes/workspaces';

type AdminWorkspace = {
    id: number;
    name: string;
    slug: string;
    archived: boolean;
    members: number;
    records: number;
    open_alerts: number;
    in_review: number;
    last_activity: string | null;
    managers: string[];
};

type Props = {
    workspaces: AdminWorkspace[];
    ingestion: {
        items_24h: number;
        items_7d: number;
        sources: number;
        failing: number;
        last_fetch: string | null;
    };
};

function AddManager({ workspace }: { workspace: AdminWorkspace }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    if (!open) {
        return (
            <Button variant="ghost" size="sm" onClick={() => setOpen(true)}>
                {t('Name a manager')}
            </Button>
        );
    }

    return (
        <Form
            {...managers.store.form(workspace.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setOpen(false)}
            className="flex w-full flex-wrap items-start gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1">
                        <Input
                            name="name"
                            required
                            placeholder={t('Name')}
                            className="h-8 w-48"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Input
                            name="email"
                            type="email"
                            required
                            placeholder={t('Institutional email')}
                            className="h-8 w-64"
                        />
                        <InputError message={errors.email} />
                    </div>
                    <Button size="sm" disabled={processing}>
                        {t('Add as manager')}
                    </Button>
                </>
            )}
        </Form>
    );
}

export default function Workspaces({ workspaces, ingestion }: Props) {
    const { t, locale } = useTranslation();
    const { flash } = usePage();
    const rename = (workspace: AdminWorkspace, name: string) =>
        name.trim() !== '' &&
        name !== workspace.name &&
        router.patch(
            update(workspace.id).url,
            { name },
            { preserveScroll: true },
        );

    const stats = [
        { label: 'Articles in the last 24 h', value: ingestion.items_24h },
        { label: 'Articles in the last 7 days', value: ingestion.items_7d },
        { label: 'Active sources', value: ingestion.sources },
        {
            label: 'Sources failing',
            value: ingestion.failing,
            alert: ingestion.failing > 0,
        },
    ];

    return (
        <>
            <Head title={t('Teams')} />

            <div className="space-y-8 px-4 py-6">
                <Heading
                    title={t('Teams')}
                    description={t(
                        'Every team at a glance. Archived teams keep their data but stop working.',
                    )}
                />

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                        {t('Ingestion')}
                    </h2>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {stats.map((stat) => (
                            <div
                                key={stat.label}
                                className="rounded-xl border p-4"
                            >
                                <p
                                    className={cn(
                                        'text-2xl font-semibold',
                                        stat.alert && 'text-red-600',
                                    )}
                                >
                                    {stat.value}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {t(stat.label)}
                                </p>
                            </div>
                        ))}
                    </div>
                    {ingestion.last_fetch && (
                        <p className="text-xs text-muted-foreground">
                            {t('Last collection :date', {
                                date: formatDateTime(
                                    ingestion.last_fetch,
                                    locale,
                                ),
                            })}
                        </p>
                    )}
                </section>

                {flash.invitationLink && (
                    <InvitationLink link={flash.invitationLink} />
                )}

                <ul className="divide-y rounded-lg border">
                    {workspaces.map((workspace) => (
                        <li
                            key={workspace.id}
                            className={cn(
                                'space-y-2 p-4',
                                workspace.archived && 'opacity-60',
                            )}
                        >
                            <div className="flex flex-wrap items-center gap-3">
                                <Input
                                    aria-label={t('Name')}
                                    defaultValue={workspace.name}
                                    className="h-8 max-w-72 font-medium"
                                    onBlur={(event) =>
                                        rename(workspace, event.target.value)
                                    }
                                />
                                {workspace.archived && (
                                    <Badge variant="secondary">
                                        {t('Archived')}
                                    </Badge>
                                )}
                                <span className="flex-1" />
                                {!workspace.archived && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                switchMethod(workspace.id).url,
                                            )
                                        }
                                    >
                                        {t('Enter')}
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.patch(
                                            update(workspace.id).url,
                                            { archived: !workspace.archived },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t(
                                        workspace.archived
                                            ? 'Restore'
                                            : 'Archive',
                                    )}
                                </Button>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    ':members members · :records records · :alerts open alerts · :review in review',
                                    {
                                        members: workspace.members,
                                        records: workspace.records,
                                        alerts: workspace.open_alerts,
                                        review: workspace.in_review,
                                    },
                                )}
                                {workspace.last_activity &&
                                    ` · ${t('last activity :date', { date: formatDateTime(workspace.last_activity, locale) })}`}
                            </p>
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="text-muted-foreground">
                                    {t('Managers')}:
                                </span>
                                {workspace.managers.length === 0 ? (
                                    <span className="font-medium text-red-600">
                                        {t('none')}
                                    </span>
                                ) : (
                                    workspace.managers.map((name) => (
                                        <Badge key={name} variant="outline">
                                            {name}
                                        </Badge>
                                    ))
                                )}
                                {!workspace.archived && (
                                    <AddManager workspace={workspace} />
                                )}
                            </div>
                        </li>
                    ))}
                </ul>

                <Form
                    {...store.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap items-start gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Input
                                    name="name"
                                    required
                                    placeholder={t('e.g. CI FEUP')}
                                    className="w-72"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>
                                {t('Create team')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Workspaces.layout = {
    breadcrumbs: [{ title: 'Teams', href: index() }],
};
