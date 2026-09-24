<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Social network credentials (Admin → Social networks). Secrets are encrypted with APP_KEY.
 */
class SocialSettings
{
    private const KEY = 'social';

    /** Secret fields, per network: never sent back to the browser. */
    public const SECRETS = ['bluesky' => ['app_password'], 'youtube' => ['api_key'], 'instagram' => ['access_token'], 'mastodon' => []];

    /** Plain fields, per network. */
    public const FIELDS = ['bluesky' => ['handle'], 'youtube' => [], 'instagram' => ['account_id'], 'mastodon' => ['instance']];

    /**
     * Decrypted values of one network, or [] when it was never configured.
     *
     * @return array<string, string|null>
     */
    public function get(string $network): array
    {
        $values = $this->stored()[$network] ?? [];

        foreach (self::SECRETS[$network] as $secret) {
            if (isset($values[$secret])) {
                $values[$secret] = Crypt::decryptString($values[$secret]);
            }
        }

        return $values;
    }

    /**
     * For the Admin form: plain fields, and whether each secret is set.
     *
     * @return array<string, array<string, string|bool|null>>
     */
    public function forForm(): array
    {
        $stored = $this->stored();
        $form = [];
        foreach (self::FIELDS as $network => $fields) {
            foreach ($fields as $field) {
                $form[$network][$field] = $stored[$network][$field] ?? null;
            }
            foreach (self::SECRETS[$network] as $secret) {
                $form[$network]['has_'.$secret] = isset($stored[$network][$secret]);
            }
        }

        return $form;
    }

    /**
     * Saves one network. An empty secret keeps the stored one; `clear` forgets the network.
     *
     * @param  array<string, string|null>  $values
     */
    public function save(string $network, array $values, bool $clear = false): void
    {
        $all = $this->stored();
        $current = $all[$network] ?? [];

        if ($clear) {
            unset($all[$network]);
        } else {
            foreach (self::FIELDS[$network] as $field) {
                $current[$field] = filled($values[$field] ?? null) ? trim((string) $values[$field]) : null;
            }
            foreach (self::SECRETS[$network] as $secret) {
                if (filled($values[$secret] ?? null)) {
                    $current[$secret] = Crypt::encryptString(trim((string) $values[$secret]));
                }
            }
            $all[$network] = $current;
        }

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $all]);
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function stored(): array
    {
        return Setting::find(self::KEY)->value ?? [];
    }
}
