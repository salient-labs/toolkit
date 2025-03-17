<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Console\HasMessageType;
use Salient\Contract\HasMessageLevel;

/**
 * @api
 */
interface MessageFormatInterface extends HasMessageLevel, HasMessageType
{
    /**
     * Format a console message
     */
    public function apply(
        string $msg1,
        ?string $msg2,
        string $prefix,
        MessageAttributesInterface $attributes
    ): string;
}
