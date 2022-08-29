<?php

declare(strict_types=1);

namespace Lkrms\Support;

use DateTimeInterface;
use DateTimeZone;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Convert;

/**
 * @property-read string $Format
 * @property-read DateTimeZone|null $Timezone
 */
final class DateFormatter implements IReadable
{
    use TFullyReadable;

    public const DEFAULT = DateTimeInterface::ATOM;

    /**
     * @internal
     * @var string
     */
    protected $Format;

    /**
     * @internal
     * @var DateTimeZone|null
     */
    protected $Timezone;

    /**
     * @param string $format
     * @param DateTimeZone|string|null $timezone
     */
    public function __construct(
        string $format = DateFormatter::DEFAULT,
        $timezone      = null
    ) {
        $this->Format   = $format;
        $this->Timezone = is_null($timezone)
            ? null
            : Convert::toTimezone($timezone);
    }

    /**
     * Apply the timezone to a copy of $date and return its formatted value
     *
     * @param DateTimeInterface $date
     * @return string
     */
    public function format(DateTimeInterface $date): string
    {
        if ($this->Timezone &&
            $this->Timezone->getName() != $date->getTimezone()->getName())
        {
            $date = Convert::toDateTimeImmutable($date)->setTimezone($this->Timezone);
        }

        return $date->format($this->Format);
    }
}
