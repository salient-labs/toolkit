<?php declare(strict_types=1);

namespace Lkrms\Console;

/**
 * A collection of character sequences that can be used to apply formatting to
 * the components of a console message
 *
 */
final class ConsoleMessageFormat
{
    private $Msg1Format;

    private $Msg2Format;

    private $PrefixFormat;

    public function __construct(ConsoleFormat $msg1Format, ConsoleFormat $msg2Format, ConsoleFormat $prefixFormat)
    {
        $this->Msg1Format   = $msg1Format;
        $this->Msg2Format   = $msg2Format;
        $this->PrefixFormat = $prefixFormat;
    }

    public function apply(string $msg1, ?string $msg2, string $prefix): string
    {
        return $this->PrefixFormat->apply($prefix)
            . $this->Msg1Format->apply($msg1)
            . ($msg2 ? $this->Msg2Format->apply($msg2) : '');
    }
}
