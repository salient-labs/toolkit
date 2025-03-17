<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\MessageFormatInterface;
use Salient\Contract\Console\ConsoleInterface as Console;

/**
 * Maps message levels and types to formats
 *
 * If multiple formats are assigned to the same level and type, the format
 * assigned last takes precedence.
 */
final class ConsoleMessageFormats
{
    /** @var array<Console::LEVEL_*,array<Console::TYPE_*,MessageFormatInterface>> */
    private array $Formats = [];
    private MessageFormatInterface $FallbackFormat;

    public function __construct(?MessageFormatInterface $fallbackFormat = null)
    {
        $this->FallbackFormat = $fallbackFormat
            ?? new NullMessageFormat();
    }

    /**
     * Assign a format to one or more message levels and types
     *
     * @param array<Console::LEVEL_*>|Console::LEVEL_* $level
     * @param array<Console::TYPE_*>|Console::TYPE_* $type
     * @return $this
     */
    public function set($level, $type, MessageFormatInterface $format)
    {
        foreach ((array) $level as $level) {
            foreach ((array) $type as $_type) {
                $this->Formats[$level][$_type] = $format;
            }
        }

        return $this;
    }

    /**
     * Get the format assigned to a message level and type
     *
     * @param Console::LEVEL_* $level
     * @param Console::TYPE_* $type
     */
    public function get(int $level, $type): MessageFormatInterface
    {
        return $this->Formats[$level][$type] ?? $this->FallbackFormat;
    }
}
