<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\MessageAttributesInterface;
use Salient\Contract\Console\Format\MessageFormatInterface;

/**
 * @api
 */
final class NullMessageFormat implements MessageFormatInterface
{
    private NullFormat $Format;

    /**
     * @api
     */
    public function __construct()
    {
        $this->Format = new NullFormat();
    }

    /**
     * @inheritDoc
     */
    public function apply(
        string $msg1,
        ?string $msg2,
        string $prefix,
        MessageAttributesInterface $attributes
    ): string {
        return (
            $prefix !== ''
                ? $this->Format->apply($prefix, $attributes->withPrefix())
                : ''
        ) . (
            $msg1 !== ''
                ? $this->Format->apply($msg1, $attributes->withMsg1())
                : ''
        ) . (
            $msg2 !== null && $msg2 !== ''
                ? $this->Format->apply($msg2, $attributes->withMsg2())
                : ''
        );
    }
}
