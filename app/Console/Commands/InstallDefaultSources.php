<?php

namespace App\Console\Commands;

use App\Enums\SourceKind;
use App\Models\Source;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:install-default-sources')]
#[Description('Add the initial source catalogue (feeds verified on 2026-09-24)')]
class InstallDefaultSources extends Command
{
    /**
     * Expresso and SIC Notícias block automated requests (HTTP 403); CM, Jornal de
     * Negócios and Sábado publish no feed. Some of them arrive through SAPO Notícias,
     * which names the original outlet in each item, and the rest through Google News.
     *
     * @var array<int, array{0: string, 1: SourceKind, 2: string, 3?: array<string, mixed>}>
     */
    private const SOURCES = [
        ['JN', SourceKind::Rss, 'https://feeds.feedburner.com/jn-ultimas'],
        ['DN', SourceKind::Rss, 'https://www.dn.pt/feed'],
        ['Observador', SourceKind::Rss, 'https://observador.pt/feed/'],
        ['Público', SourceKind::Rss, 'https://feeds.feedburner.com/PublicoRSS'],
        ['SAPO Notícias', SourceKind::Rss, 'https://noticias.sapo.pt/rss', ['outlet_from_author' => true]],
        ['Renascença', SourceKind::Rss, 'https://rr.pt/rssfeed-ultimas'],
        ['RTP Notícias', SourceKind::Rss, 'https://www.rtp.pt/noticias/rss'],
        ['CNN Portugal', SourceKind::Rss, 'https://cnnportugal.iol.pt/rss.xml'],
        ['TSF', SourceKind::Rss, 'https://feeds.feedburner.com/tsf-ultimas'],
        ['ECO', SourceKind::Rss, 'https://eco.sapo.pt/feed/'],
        ['Visão', SourceKind::Rss, 'https://visao.pt/feed/'],
        ['Notícias ao Minuto', SourceKind::Rss, 'https://www.noticiasaominuto.com/rss/ultima-hora'],
        ['JPN', SourceKind::Rss, 'https://jpn.up.pt/feed/'],
        ['Google News: Universidade do Porto', SourceKind::GoogleNews, 'https://news.google.com/rss/search?q=%22Universidade+do+Porto%22+OR+%22U.Porto%22&hl=pt-PT&gl=PT&ceid=PT:pt-150'],
    ];

    public function handle(): int
    {
        foreach (self::SOURCES as $row) {
            [$name, $kind, $url] = $row;
            $config = ['language' => 'pt', ...($row[3] ?? [])];
            $source = Source::firstOrCreate(['url' => $url], ['name' => $name, 'kind' => $kind, 'config' => $config]);

            // Sources installed before a setting existed get it; nothing a person changed is overwritten.
            if (array_diff_key($config, $source->config ?? []) !== []) {
                $source->forceFill(['config' => [...$config, ...($source->config ?? [])]])->save();
            }
            $this->line("✓ {$name}");
        }

        return self::SUCCESS;
    }
}
