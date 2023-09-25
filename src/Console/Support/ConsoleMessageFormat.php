<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleAttribute as Attribute;
use Lkrms\Console\Contract\IConsoleFormat as Format;

/**
 * Applies target-defined formats to the components of a console message
 */
final class ConsoleMessageFormat
{
    private Format $Msg1Format;

    private Format $Msg2Format;

    private Format $PrefixFormat;

    private static ConsoleMessageFormat $DefaultMessageFormat;

    public function __construct(
        Format $msg1Format,
        Format $msg2Format,
        Format $prefixFormat
    ) {
        $this->Msg1Format = $msg1Format;
        $this->Msg2Format = $msg2Format;
        $this->PrefixFormat = $prefixFormat;
    }

    /**
     * Format a message before it is written to the target
     *
     * @param array<Attribute::*,mixed> $attributes
     */
    public function apply(string $msg1, ?string $msg2, string $prefix, array $attributes = []): string
    {
        return
            ($prefix !== '' ? $this->PrefixFormat->apply(
                $prefix, [Attribute::IS_PREFIX => true] + $attributes
            ) : '')
            . ($msg1 !== '' ? $this->Msg1Format->apply(
                $msg1, [Attribute::IS_MSG1 => true] + $attributes
            ) : '')
            . (($msg2 ?? '') !== '' ? $this->Msg2Format->apply(
                $msg2, [Attribute::IS_MSG2 => true] + $attributes
            ) : '');
    }

    /**
     * Get an instance that doesn't apply any formatting to messages
     */
    public static function getDefaultMessageFormat(): self
    {
        return self::$DefaultMessageFormat
            ?? (self::$DefaultMessageFormat = self::createDefaultMessageFormat());
    }

    private static function createDefaultMessageFormat(): self
    {
        $format = ConsoleFormat::getDefaultFormat();

        return new self(
            $format,
            $format,
            $format,
        );
    }
}
