<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Console\HasMessageType;
use Salient\Contract\Core\Immutable;
use Salient\Contract\HasMessageLevel;

/**
 * @api
 */
interface MessageAttributesInterface extends
    Immutable,
    HasMessageLevel,
    HasMessageType
{
    /**
     * Get the message level
     *
     * @return MessageAttributesInterface::LEVEL_*
     */
    public function getLevel(): int;

    /**
     * Get the message type
     *
     * @return MessageAttributesInterface::TYPE_*
     */
    public function getType(): int;

    /**
     * Check if the instance applies to the first part of a message
     */
    public function isMsg1(): bool;

    /**
     * Check if the instance applies to the second part of a message
     */
    public function isMsg2(): bool;

    /**
     * Check if the instance applies to a message prefix
     */
    public function isPrefix(): bool;

    /**
     * Get an instance that applies to the first part of a message
     *
     * @return static
     */
    public function withMsg1();

    /**
     * Get an instance that applies to the second part of a message
     *
     * @return static
     */
    public function withMsg2();

    /**
     * Get an instance that applies to a message prefix
     *
     * @return static
     */
    public function withPrefix();
}
