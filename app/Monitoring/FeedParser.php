<?php

namespace App\Monitoring;

use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * RSS 2.0 and Atom feeds to FeedEntry objects. Summaries are plain text,
 * shortened: only metadata is kept, never the article body.
 */
class FeedParser
{
    /**
     * @return array<int, FeedEntry>
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new RuntimeException('The feed is not valid XML.');
        }

        $entries = [];

        foreach ($document->channel->item ?? [] as $item) {
            $entries[] = new FeedEntry(
                headline: self::text((string) $item->title),
                url: trim((string) $item->link),
                summary: self::summary((string) $item->description),
                publishedAt: self::date((string) $item->pubDate),
                outlet: isset($item->source) ? self::text((string) $item->source) : null,
                author: isset($item->author) ? self::text((string) $item->author) : null,
            );
        }

        $document->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        foreach ($document->xpath('//atom:entry') ?: [] as $entry) {
            $link = '';
            foreach ($entry->link as $candidate) {
                if (in_array((string) $candidate['rel'], ['', 'alternate'], true)) {
                    $link = (string) $candidate['href'];
                    break;
                }
            }

            $entries[] = new FeedEntry(
                headline: self::text((string) $entry->title),
                url: trim($link),
                summary: self::summary((string) ($entry->summary ?? $entry->content)),
                publishedAt: self::date((string) ($entry->published ?? $entry->updated)),
            );
        }

        return array_values(array_filter($entries, fn (FeedEntry $entry) => $entry->headline !== '' && $entry->url !== ''));
    }

    public static function text(string $value): string
    {
        // Zero-width characters (some feeds start titles with one) would defeat story grouping.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    public static function summary(string $value): ?string
    {
        $text = self::text($value);

        return $text === '' ? null : mb_strimwidth($text, 0, 600, '…');
    }

    public static function date(string $value): ?CarbonImmutable
    {
        try {
            return $value === '' ? null : CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
