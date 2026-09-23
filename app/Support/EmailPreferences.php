<?php

namespace App\Support;

use App\Models\User;

/**
 * What each user also wants by email. Everything is always in the app; email
 * only goes out once the SMTP server is configured in Administration → Email.
 */
class EmailPreferences
{
    /** @var array<string, bool> */
    public const DEFAULTS = [
        'assignments' => true,
        'reviews' => true,
        'reminders' => true,
        'alerts' => true,
        'daily_briefing' => false,
        'weekly_briefing' => false,
    ];

    public function __construct(private MailSettings $mail) {}

    /**
     * @return array<string, bool>
     */
    public static function of(User $user): array
    {
        return array_merge(self::DEFAULTS, array_intersect_key($user->email_preferences ?? [], self::DEFAULTS));
    }

    public function wants(User $user, string $kind): bool
    {
        return $this->mail->isConfigured() && $user->deactivated_at === null && (self::of($user)[$kind] ?? false);
    }
}
