<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\FormatInterface as Format;
use Salient\Contract\Console\Format\MessageAttributesInterface;
use Salient\Contract\Console\Format\MessageFormatInterface;

/**
 * @api
 */
class MessageFormat implements MessageFormatInterface
{
    private Format $Msg1Format;
    private Format $Msg2Format;
    private Format $PrefixFormat;

    /**
     * @api
     */
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
                ? $this->PrefixFormat->apply($prefix, $attributes->withPrefix())
                : ''
        ) . (
            $msg1 !== ''
                ? $this->Msg1Format->apply($msg1, $attributes->withMsg1())
                : ''
        ) . (
            $msg2 !== null && $msg2 !== ''
                ? $this->Msg2Format->apply($msg2, $attributes->withMsg2())
                : ''
        );
    }
}
