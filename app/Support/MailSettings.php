<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * SMTP configuration stored in the database (Admin → Email) instead of .env.
 * The SMTP password is encrypted with APP_KEY.
 */
class MailSettings
{
    private const KEY = 'mail';

    /**
     * Stored values without the password, for the Admin form.
     *
     * @return array{host: string, port: int, encryption: string, username: ?string, from_address: string, from_name: string, has_password: bool}|null
     */
    public function forForm(): ?array
    {
        $value = $this->stored();

        if ($value === null) {
            return null;
        }

        return [
            'host' => $value['host'],
            'port' => $value['port'],
            'encryption' => $value['encryption'],
            'username' => $value['username'],
            'from_address' => $value['from_address'],
            'from_name' => $value['from_name'],
            'has_password' => $value['password'] !== null,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->stored() !== null;
    }

    /**
     * @param  array{host: string, port: int, encryption: string, username: ?string, password: ?string, from_address: string, from_name: string}  $data
     */
    public function save(array $data): void
    {
        // An empty password field keeps the stored one.
        $data['password'] = filled($data['password'])
            ? Crypt::encryptString($data['password'])
            : ($this->stored()['password'] ?? null);

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $data]);

        $this->apply();

        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    /**
     * Point Laravel's mailer at the stored SMTP server. Called when the mail
     * manager is first resolved, so requests that never send mail skip the query.
     */
    public function apply(): void
    {
        $value = $this->stored();

        if ($value === null) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $value['encryption'] === 'ssl' ? 'smtps' : 'smtp',
                'host' => $value['host'],
                'port' => $value['port'],
                'username' => $value['username'],
                'password' => $value['password'] !== null ? Crypt::decryptString($value['password']) : null,
                'timeout' => 10,
            ],
            'mail.from' => [
                'address' => $value['from_address'],
                'name' => $value['from_name'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(): ?array
    {
        return Setting::find(self::KEY)?->value;
    }
}
