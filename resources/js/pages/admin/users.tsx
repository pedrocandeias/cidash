import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { index, twoFactorReset, update } from '@/routes/admin/users';
import * as memberships from '@/routes/admin/users/memberships';

type Role = 'member' | 'editor' | 'manager';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
    pending: boolean;
    deactivated: boolean;
    two_factor: boolean;
    is_me: boolean;
    memberships: { workspace_id: number; workspace: string; role: Role }[];
};

const roleLabels: Record<Role, string> = {
    member: 'Member',
    editor: 'Editor',
    manager: 'Manager',
};

function AddMembership({
    user,
    workspaces,
}: {
    user: AdminUser;
    workspaces: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const [workspace, setWorkspace] = useState('');
    const available = workspaces.filter(
        (candidate) =>
            !user.memberships.some(
                (membership) => membership.workspace_id === candidate.id,
            ),
    );

    if (available.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center gap-2">
            <Select value={workspace} onValueChange={setWorkspace}>
                <SelectTrigger
                    className="h-8 w-48"
                    aria-label={t('Add to team')}
                >
                    <SelectValue placeholder={t('Add to team…')} />
                </SelectTrigger>
                <SelectContent>
                    {available.map((candidate) => (
                        <SelectItem
                            key={candidate.id}
                            value={String(candidate.id)}
                        >
                            {candidate.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {workspace && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        router.post(
                            memberships.store(user.id).url,
                            { workspace_id: workspace, role: 'member' },
                            { preserveScroll: true },
                        );
                        setWorkspace('');
                    }}
                >
                    {t('Add')}
                </Button>
            )}
        </div>
    );
}

export default function AdminUsers({
    users,
    workspaces,
}: {
    users: AdminUser[];
    workspaces: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { errors } = usePage().props;

    return (
        <>
            <Head title={t('Users')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('Users')}
                    description={t(
                        'Every account of this CIDASH, with its teams and roles.',
                    )}
                />
                <InputError
                    message={errors?.user ?? errors?.role ?? errors?.workspace}
                />

                <ul className="divide-y rounded-lg border">
                    {users.map((user) => (
                        <li key={user.id} className="space-y-3 p-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="min-w-48 flex-1">
                                    <p className="text-sm font-medium">
                                        {user.name}
                                        {user.is_me && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                ({t('you')})
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {user.email}
                                    </p>
                                </div>
                                {user.is_super_admin && (
                                    <Badge>{t('Super admin')}</Badge>
                                )}
                                {user.pending && (
                                    <Badge variant="secondary">
                                        {t('Pending')}
                                    </Badge>
                                )}
                                {user.deactivated && (
                                    <Badge variant="destructive">
                                        {t('Deactivated')}
                                    </Badge>
                                )}
                                {user.two_factor && (
                                    <Badge variant="outline">2FA</Badge>
                                )}

                                {!user.is_me && (
                                    <>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.patch(
                                                    update(user.id).url,
                                                    {
                                                        is_super_admin:
                                                            !user.is_super_admin,
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t(
                                                user.is_super_admin
                                                    ? 'Remove super admin'
                                                    : 'Make super admin',
                                            )}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.patch(
                                                    update(user.id).url,
                                                    {
                                                        active: user.deactivated,
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t(
                                                user.deactivated
                                                    ? 'Reactivate'
                                                    : 'Deactivate',
                                            )}
                                        </Button>
                                    </>
                                )}
                                {user.two_factor && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(
                                                twoFactorReset(user.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('Reset 2FA')}
                                    </Button>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                {user.memberships.map((membership) => (
                                    <div
                                        key={membership.workspace_id}
                                        className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm"
                                    >
                                        <span>{membership.workspace}</span>
                                        <Select
                                            value={membership.role}
                                            onValueChange={(role) =>
                                                router.patch(
                                                    memberships.update([
                                                        user.id,
                                                        membership.workspace_id,
                                                    ]).url,
                                                    { role },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                className="h-7 w-28 border-0 shadow-none"
                                                aria-label={t('Role')}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(roleLabels).map(
                                                    ([value, label]) => (
                                                        <SelectItem
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {t(label)}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <button
                                            type="button"
                                            className="text-muted-foreground hover:text-foreground"
                                            aria-label={t('Remove from :team', {
                                                team: membership.workspace,
                                            })}
                                            onClick={() =>
                                                router.delete(
                                                    memberships.destroy([
                                                        user.id,
                                                        membership.workspace_id,
                                                    ]).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            ×
                                        </button>
                                    </div>
                                ))}
                                <AddMembership
                                    user={user}
                                    workspaces={workspaces}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

AdminUsers.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
