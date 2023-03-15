<?php declare(strict_types=1);

namespace Lkrms\Support\DateParser;

use DateTimeImmutable;
use DateTimeZone;
use Lkrms\Contract\IDateParser;

/**
 * Returns a DateTimeImmutable from a callback if a regular expression matches
 *
 */
final class RegexDateParser implements IDateParser
{
    /**
     * @var string
     */
    private $Pattern;

    /**
     * @var callable
     */
    private $Callback;

    /**
     * @param callable $callback
     * ```php
     * fn(array $matches, ?DateTimeZone $timezone): DateTimeImmutable
     * ```
     */
    public function __construct(string $pattern, callable $callback)
    {
        $this->Pattern  = $pattern;
        $this->Callback = $callback;
    }

    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        if (preg_match($this->Pattern, $value, $matches)) {
            return ($this->Callback)($matches, $timezone);
        }

        return null;
    }

    public static function dotNet(): IDateParser
    {
        return new self(
            '/^\/Date\((?P<seconds>[0-9]+)(?P<milliseconds>[0-9]{3})(?P<offset>[-+][0-9]{4})?\)\/$/',
            function (array $matches, ?DateTimeZone $timezone): DateTimeImmutable {
                $date = new DateTimeImmutable(
                    sprintf('@%s.%s',
                            $matches['seconds'],
                            $matches['milliseconds'])
                );
                if (!$timezone && ($matches['offset'] ?? null)) {
                    $timezone = new DateTimeZone($matches['offset']);
                }

                return $timezone
                    ? $date->setTimezone($timezone)
                    : $date;
            }
        );
    }
}
