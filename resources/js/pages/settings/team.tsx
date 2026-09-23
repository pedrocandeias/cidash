import { Form, Head, router, usePage } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/lib/i18n';
import { destroy, index, invitation, store, update } from '@/routes/team';

type Role = 'member' | 'editor' | 'manager';

type Member = {
    id: number;
    name: string;
    email: string;
    role: Role;
    pending: boolean;
    is_me: boolean;
};

type Props = {
    team: string;
    mailConfigured: boolean;
    members: Member[];
};

const roles: { value: Role; label: string }[] = [
    { value: 'member', label: 'Member' },
    { value: 'editor', label: 'Editor' },
    { value: 'manager', label: 'Manager' },
];

function RoleSelect({
    id,
    name,
    value,
    onChange,
}: {
    id?: string;
    name?: string;
    value?: Role;
    onChange?: (role: Role) => void;
}) {
    const { t } = useTranslation();

    return (
        <Select
            name={name}
            defaultValue={onChange ? undefined : 'member'}
            value={onChange ? value : undefined}
            onValueChange={(role) => onChange?.(role as Role)}
        >
            <SelectTrigger id={id} className="w-36">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                        {t(role.label)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function InvitationLink({ link }: { link: string }) {
    const { t } = useTranslation();
    const [copied, copy] = useClipboard();

    return (
        <div className="space-y-2 rounded-lg border p-4">
            <p className="text-sm font-medium">{t('Invitation link')}</p>
            <p className="text-sm text-muted-foreground">
                {t(
                    'Send this link to the person. It is valid for 7 days and can only be used once.',
                )}
            </p>
            <div className="flex gap-2">
                <Input
                    value={link}
                    readOnly
                    onFocus={(e) => e.target.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => copy(link)}
                    aria-label={t('Copy link')}
                >
                    {copied === link ? <Check /> : <Copy />}
                </Button>
            </div>
        </div>
    );
}

export default function Team({ team, mailConfigured, members }: Props) {
    const { t } = useTranslation();
    const { flash, props } = usePage();
    const roleError = props.errors?.role;

    return (
        <>
            <Head title={t('Team')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Add member')}
                    description={t(
                        'Add a person to :team. New accounts receive an invitation to set their password.',
                        { team },
                    )}
                />

                {!mailConfigured && (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Email is not configured: invitation links are shown here to be sent by hand.',
                        )}
                    </p>
                )}

                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('Name')}</Label>
                                    <Input id="name" name="name" required />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        {t('Email address')}
                                    </Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>
                            <div className="flex items-end gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="role">{t('Role')}</Label>
                                    <RoleSelect id="role" name="role" />
                                </div>
                                <Button disabled={processing}>
                                    {t('Add member')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {flash.invitationLink && (
                    <InvitationLink link={flash.invitationLink} />
                )}
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Members')}
                    description={t(
                        'Editors can approve content. Managers can also manage the team and its settings.',
                    )}
                />

                <InputError message={roleError} />

                <ul className="divide-y rounded-lg border">
                    {members.map((member) => (
                        <li
                            key={member.id}
                            className="flex flex-wrap items-center gap-3 p-4"
                        >
                            <div className="min-w-48 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {member.name}
                                    {member.is_me && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            ({t('you')})
                                        </span>
                                    )}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>

                            {member.pending && (
                                <Badge variant="secondary">
                                    {t('Pending')}
                                </Badge>
                            )}

                            <RoleSelect
                                value={member.role}
                                onChange={(role) =>
                                    router.patch(
                                        update(member.id).url,
                                        { role },
                                        { preserveScroll: true },
                                    )
                                }
                            />

                            {member.pending && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            invitation(member.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('Resend invitation')}
                                </Button>
                            )}

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="ghost" size="sm">
                                        {t('Remove')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        {t('Remove :name from the team?', {
                                            name: member.name,
                                        })}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t(
                                            'They will lose access to this team. Their account and other teams are not affected.',
                                        )}
                                    </DialogDescription>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                {t('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <DialogClose asChild>
                                            <Button
                                                variant="destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        destroy(member.id).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {t('Remove')}
                                            </Button>
                                        </DialogClose>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

Team.layout = {
    breadcrumbs: [
        {
            title: 'Team',
            href: index(),
        },
    ],
};
