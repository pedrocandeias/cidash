<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The CIDASH version shown in the footer: the VERSION file the dist build writes,
 * or, in development, the latest release in CHANGELOG.md.
 */
class AppVersion
{
    public static function current(): ?string
    {
        return once(function () {
            if (File::exists(base_path('VERSION'))) {
                return trim(File::get(base_path('VERSION'))) ?: null;
            }

            if (File::exists(base_path('CHANGELOG.md')) && preg_match('/^## (\d+\.\d+\.\d+)/m', File::get(base_path('CHANGELOG.md')), $match)) {
                return $match[1];
            }

            return null;
        });
    }
}
