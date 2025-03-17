<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\MessageAttributesInterface;
use Salient\Contract\Console\Format\MessageFormatInterface;

/**
 * @api
 */
final class NullMessageFormat implements MessageFormatInterface
{
    /**
     * @inheritDoc
     */
    public function apply(
        string $msg1,
        ?string $msg2,
        string $prefix,
        MessageAttributesInterface $attributes
    ): string {
        return $prefix . $msg1 . $msg2;
    }
}
