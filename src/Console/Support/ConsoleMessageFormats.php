<?php declare(strict_types=1);

namespace Lkrms\Console\Support;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;
use Lkrms\Console\Support\ConsoleMessageFormat as MessageFormat;

/**
 * Maps message levels and types to target-defined formats
 *
 * If multiple formats are assigned to the same {@see Level} and {@see Type},
 * the format assigned last takes precedence.
 */
final class ConsoleMessageFormats
{
    /**
     * @var array<Level::*,array<Type::*,MessageFormat>>
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
     * @param array<Type::*>|Type::* $type
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
     * @param Type::* $type
     */
    public function get($level, $type): MessageFormat
    {
        return $this->Formats[$level][$type] ?? $this->FallbackFormat;
    }
}
