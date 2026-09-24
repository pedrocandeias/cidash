<?php

namespace App\Console\Commands;

use App\Social\SocialCollector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:fetch-social {--all : Collect every configured network, even if not due}')]
#[Description('Collect social posts with the hashtags the teams follow and file them as mentions')]
class FetchSocial extends Command
{
    public function handle(SocialCollector $collector): int
    {
        foreach ($collector->run($this->option('all')) as $network => $result) {
            is_int($result)
                ? $this->line("{$network}: {$result} new mentions")
                : $this->warn("{$network}: {$result}");
        }

        return self::SUCCESS;
    }
}
