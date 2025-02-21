<?php declare(strict_types=1);

namespace Salient\Console\Support;

use Salient\Console\Support\ConsoleMessageFormat as MessageFormat;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Console\ConsoleMessageType as MessageType;

/**
 * Maps message levels and types to formats
 *
 * If multiple formats are assigned to the same level and type, the format
 * assigned last takes precedence.
 */
final class ConsoleMessageFormats
{
    /** @var array<Console::LEVEL_*,array<MessageType::*,MessageFormat>> */
    private array $Formats = [];
    private MessageFormat $FallbackFormat;

    public function __construct(?MessageFormat $fallbackFormat = null)
    {
        $this->FallbackFormat = $fallbackFormat
            ?: MessageFormat::getDefaultMessageFormat();
    }

    /**
     * Assign a format to one or more message levels and types
     *
     * @param array<Console::LEVEL_*>|Console::LEVEL_* $level
     * @param array<MessageType::*>|MessageType::* $type
     * @return $this
     */
    public function set($level, $type, MessageFormat $format)
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
     * @param MessageType::* $type
     */
    public function get(int $level, $type): MessageFormat
    {
        return $this->Formats[$level][$type] ?? $this->FallbackFormat;
    }
}
