<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a read-only iCalendar (RFC 5545) document from dated cards, so a board's
 * (or a user's) schedule can be subscribed to from Google / Apple / Outlook Calendar.
 */
class IcalFeedService
{
    /**
     * @param  Collection<int, Card>  $cards  each with `board` (and ideally `list`) loaded
     */
    public function build(string $calendarName, Collection $cards): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Board//Board Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($calendarName),
            'NAME:'.$this->escape($calendarName),
            // Hint subscribers to re-poll hourly instead of their (often daily) default.
            // Honoured by Apple Calendar / Thunderbird; Outlook uses X-PUBLISHED-TTL.
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        $stamp = $this->utc(Carbon::now());
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'board';

        foreach ($cards as $card) {
            if ($card->due_at === null && $card->start_at === null) {
                continue;
            }

            $start = $card->start_at ?? $card->due_at;
            $end = $card->due_at ?? $card->start_at;
            if ($end->equalTo($start)) {
                $end = $end->copy()->addMinutes(30);
            }

            $context = trim(implode(' · ', array_filter([$card->board?->name, $card->list?->name])));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:card-'.$card->public_id.'@'.$host;
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART:'.$this->utc($start);
            $lines[] = 'DTEND:'.$this->utc($end);
            $lines[] = 'SUMMARY:'.$this->escape($card->title);
            if ($context !== '') {
                $lines[] = 'DESCRIPTION:'.$this->escape($context);
            }
            if ($card->board) {
                $lines[] = 'URL:'.route('boards.show', ['board' => $card->board, 'card' => $card->public_id]);
            }
            $lines[] = 'STATUS:'.($card->completed_at ? 'COMPLETED' : 'CONFIRMED');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line): string => $this->fold($line), $lines))."\r\n";
    }

    private function utc(Carbon $dt): string
    {
        return $dt->copy()->utc()->format('Ymd\THis\Z');
    }

    /** Escape an RFC 5545 TEXT value (backslash first). */
    private function escape(string $value): string
    {
        return str_replace(['\\', "\r", "\n", ';', ','], ['\\\\', '', '\\n', '\\;', '\\,'], $value);
    }

    /** Fold a content line at 73 octets, on UTF-8 char boundaries (CRLF + space). */
    private function fold(string $line): string
    {
        if (strlen($line) <= 73) {
            return $line;
        }

        $out = '';
        $len = 0;

        foreach (mb_str_split($line) as $char) {
            $bytes = strlen($char);
            if ($len + $bytes > 73) {
                $out .= "\r\n ";
                $len = 1;
            }
            $out .= $char;
            $len += $bytes;
        }

        return $out;
    }
}
