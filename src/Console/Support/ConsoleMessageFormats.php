<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Console\Support\ConsoleMessageFormat as MessageFormat;

/**
 * Maps message levels and types to formats
 *
 * If multiple formats are assigned to the same {@see Level} and
 * {@see MessageType}, the format assigned last takes precedence.
 */
final class ConsoleMessageFormats
{
    /**
     * @var array<Level::*,array<MessageType::*,MessageFormat>>
     */
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
     * @param array<Level::*>|Level::* $level
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
     * @param Level::* $level
     * @param MessageType::* $type
     */
    public function get($level, $type): MessageFormat
    {
        return $this->Formats[$level][$type] ?? $this->FallbackFormat;
    }
}
