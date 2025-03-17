<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Console\HasMessageType;
use Salient\Contract\Console\HasMessageTypes;
use Salient\Contract\HasMessageLevel;
use Salient\Contract\HasMessageLevels;

/**
 * @api
 */
interface FormatInterface extends
    HasTag,
    HasMessageLevel,
    HasMessageLevels,
    HasMessageType,
    HasMessageTypes
{
    /**
     * Format a string
     *
     * @param TagAttributesInterface|MessageAttributesInterface|null $attributes
     */
    public function apply(string $string, $attributes = null): string;
}
