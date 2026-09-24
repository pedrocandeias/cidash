<?php

namespace App\Console\Commands;

use App\Enums\SourceKind;
use App\Models\Source;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:install-default-sources')]
#[Description('Add the initial source catalogue (feeds verified on 2026-09-23)')]
class InstallDefaultSources extends Command
{
    /**
     * Expresso blocks automated requests (HTTP 403, robots.txt): it is covered
     * through Google News instead of being scraped.
     */
    private const SOURCES = [
        ['JN', SourceKind::Rss, 'https://feeds.feedburner.com/jn-ultimas'],
        ['DN', SourceKind::Rss, 'https://www.dn.pt/feed'],
        ['Observador', SourceKind::Rss, 'https://observador.pt/feed/'],
        ['Público', SourceKind::Rss, 'https://feeds.feedburner.com/PublicoRSS'],
        ['SAPO Notícias', SourceKind::Rss, 'https://noticias.sapo.pt/rss'],
        ['Renascença', SourceKind::Rss, 'https://rr.pt/rssfeed-ultimas'],
        ['Google News: Universidade do Porto', SourceKind::GoogleNews, 'https://news.google.com/rss/search?q=%22Universidade+do+Porto%22+OR+%22U.Porto%22&hl=pt-PT&gl=PT&ceid=PT:pt-150'],
    ];

    public function handle(): int
    {
        foreach (self::SOURCES as [$name, $kind, $url]) {
            Source::firstOrCreate(['url' => $url], ['name' => $name, 'kind' => $kind, 'config' => ['language' => 'pt']]);
            $this->line("✓ {$name}");
        }

        return self::SUCCESS;
    }
}
