<?php

namespace App\Calendar;

use App\Enums\EventStatus;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;

/**
 * iCalendar (RFC 5545) for calendar events: timed events in UTC, all-day
 * events as dates with the exclusive end iCalendar expects.
 */
class Ics
{
    /**
     * @param  iterable<CalendarEvent>  $events
     */
    public static function calendar(iterable $events, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//U.Porto//CIDASH//PT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::text($name),
            'X-WR-TIMEZONE:'.config('app.timezone'),
        ];

        foreach ($events as $event) {
            array_push($lines, ...self::event($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * @return array<int, string>
     */
    private static function event(CalendarEvent $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$event->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.self::utc($event->updated_at ?? CarbonImmutable::now()),
        ];

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$event->start_at->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.($event->end_at ?? $event->start_at)->addDay()->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.self::utc($event->start_at);
            $lines[] = 'DTEND:'.self::utc($event->end_at ?? $event->start_at->addHour());
        }

        $lines[] = 'SUMMARY:'.self::text($event->title);
        if ($event->location) {
            $lines[] = 'LOCATION:'.self::text($event->location);
        }
        if ($event->description) {
            $lines[] = 'DESCRIPTION:'.self::text($event->description);
        }
        $lines[] = 'URL:'.route('events.show', $event);
        $lines[] = 'STATUS:'.match ($event->status) {
            EventStatus::Tentative => 'TENTATIVE',
            EventStatus::Cancelled => 'CANCELLED',
            default => 'CONFIRMED',
        };
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private static function utc(\DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->utc()->format('Ymd\THis\Z');
    }

    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * Lines longer than 75 octets continue on the next line after a space, without splitting characters.
     */
    private static function fold(string $line): string
    {
        $folded = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > ($folded === '' ? 75 : 74)) {
                $folded .= ($folded === '' ? '' : "\r\n ").$current;
                $current = '';
            }
            $current .= $char;
        }

        return $folded === '' ? $current : $folded."\r\n ".$current;
    }
}
