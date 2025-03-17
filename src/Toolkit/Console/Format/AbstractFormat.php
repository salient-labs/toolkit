<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface;
use Salient\Contract\Console\Target\TargetInterface;
use Salient\Contract\Console\ConsoleInterface as Console;
use Closure;

/**
 * @internal
 */
abstract class AbstractFormat implements FormatInterface
{
    /** @var array<Console::LEVEL_*,string> */
    protected const LEVEL_PREFIX_MAP = Formatter::DEFAULT_LEVEL_PREFIX_MAP;
    /** @var array<Console::TYPE_*,string> */
    protected const TYPE_PREFIX_MAP = Formatter::DEFAULT_TYPE_PREFIX_MAP;

    /**
     * Get a formatter for the format
     *
     * @param TargetInterface|(Closure(): int|null)|null $targetOrWidthCallback
     */
    public static function getFormatter($targetOrWidthCallback = null): Formatter
    {
        return new Formatter(
            static::getTagFormats(),
            static::getMessageFormats(),
            $targetOrWidthCallback instanceof TargetInterface
                ? fn() => $targetOrWidthCallback->getWidth()
                : $targetOrWidthCallback,
            static::LEVEL_PREFIX_MAP,
            static::TYPE_PREFIX_MAP,
        );
    }

    /**
     * Get tag formats, or null if the format does not provide them
     */
    protected static function getTagFormats(): ?TagFormats
    {
        return null;
    }

    /**
     * Get message formats, or null if the format does not provide them
     */
    protected static function getMessageFormats(): ?MessageFormats
    {
        return null;
    }
}
