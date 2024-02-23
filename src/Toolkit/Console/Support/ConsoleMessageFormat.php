<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Console\Contract\ConsoleFormatInterface as Format;
use Salient\Console\Support\ConsoleMessageAttributes as MessageAttributes;

/**
 * Applies formats to the components of a console message
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
     */
    public function apply(string $msg1, ?string $msg2, string $prefix, MessageAttributes $attributes): string
    {
        return
            ($prefix !== '' ? $this->PrefixFormat->apply($prefix, $attributes->withIsPrefix()) : '')
            . ($msg1 !== '' ? $this->Msg1Format->apply($msg1, $attributes->withIsMsg1()) : '')
            . ((string) $msg2 !== '' ? $this->Msg2Format->apply($msg2, $attributes->withIsMsg2()) : '');
    }

    /**
     * Get an instance that doesn't apply any formatting to messages
     */
    public static function getDefaultMessageFormat(): self
    {
        return self::$DefaultMessageFormat
            ??= new self(
                ConsoleFormat::getDefaultFormat(),
                ConsoleFormat::getDefaultFormat(),
                ConsoleFormat::getDefaultFormat(),
            );
    }
}
