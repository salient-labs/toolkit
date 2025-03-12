<?php declare(strict_types=1);

namespace Salient\Contract\Console\Format;

use Salient\Contract\Core\Immutable;

interface ConsoleMessageAttributesInterface extends Immutable
{
    /**
     * Get an instance for the first part of a message
     *
     * @return static
     */
    public function withIsMsg1(bool $value = true);

    /**
     * Get an instance for the second part of a message
     *
     * @return static
     */
    public function withIsMsg2(bool $value = true);

    /**
     * Get an instance for a message prefix
     *
     * @return static
     */
    public function withIsPrefix(bool $value = true);
}
