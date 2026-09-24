import { Form, Head } from '@inertiajs/react';
import MailSettingsController from '@/actions/App/Http/Controllers/Admin/MailSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { edit } from '@/routes/admin/email';

type Props = {
    settings: {
        host: string;
        port: number;
        encryption: 'starttls' | 'ssl';
        username: string | null;
        from_address: string;
        from_name: string;
        has_password: boolean;
    } | null;
};

export default function EmailSettings({ settings }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Email settings')} />

            <div className="max-w-xl space-y-10 px-4 py-6">
                <Heading
                    title={t('Email settings')}
                    description={t(
                        'SMTP server used to send invitations, password resets and notifications.',
                    )}
                />

                {!settings && (
                    <p className="rounded-lg border border-warning/30 bg-warning-surface p-4 text-sm text-foreground">
                        {t(
                            'Email is not configured. Invitation and password links must be copied and sent by hand.',
                        )}
                    </p>
                )}

                <Form
                    {...MailSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['password']}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                <div className="grid gap-2">
                                    <Label htmlFor="host">{t('Server')}</Label>
                                    <Input
                                        id="host"
                                        name="host"
                                        defaultValue={settings?.host}
                                        placeholder="smtp.up.pt"
                                        required
                                    />
                                    <InputError message={errors.host} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="port">{t('Port')}</Label>
                                    <Input
                                        id="port"
                                        name="port"
                                        type="number"
                                        defaultValue={settings?.port ?? 587}
                                        required
                                    />
                                    <InputError message={errors.port} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="encryption">
                                    {t('Encryption')}
                                </Label>
                                <Select
                                    name="encryption"
                                    defaultValue={
                                        settings?.encryption ?? 'starttls'
                                    }
                                >
                                    <SelectTrigger id="encryption">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="starttls">
                                            {t('STARTTLS (usually port 587)')}
                                        </SelectItem>
                                        <SelectItem value="ssl">
                                            {t('SSL/TLS (usually port 465)')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.encryption} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="username">
                                    {t('Username')}
                                </Label>
                                <Input
                                    id="username"
                                    name="username"
                                    defaultValue={settings?.username ?? ''}
                                    autoComplete="off"
                                />
                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    autoComplete="new-password"
                                    placeholder={
                                        settings?.has_password
                                            ? t(
                                                  'Leave empty to keep the current password',
                                              )
                                            : ''
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="from_address">
                                        {t('Sender address')}
                                    </Label>
                                    <Input
                                        id="from_address"
                                        name="from_address"
                                        type="email"
                                        defaultValue={settings?.from_address}
                                        placeholder="cidash@up.pt"
                                        required
                                    />
                                    <InputError message={errors.from_address} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="from_name">
                                        {t('Sender name')}
                                    </Label>
                                    <Input
                                        id="from_name"
                                        name="from_name"
                                        defaultValue={
                                            settings?.from_name ?? 'CIDASH'
                                        }
                                        required
                                    />
                                    <InputError message={errors.from_name} />
                                </div>
                            </div>

                            <Button disabled={processing}>{t('Save')}</Button>
                        </>
                    )}
                </Form>

                {settings && (
                    <div className="space-y-4">
                        <Heading
                            variant="small"
                            title={t('Test email')}
                            description={t(
                                'Send a test email to your address with the saved settings.',
                            )}
                        />
                        <Form {...MailSettingsController.test.form()}>
                            {({ processing }) => (
                                <Button variant="outline" disabled={processing}>
                                    {t('Send test email')}
                                </Button>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}

EmailSettings.layout = {
    breadcrumbs: [
        {
            title: 'Email settings',
            href: edit(),
        },
    ],
};
