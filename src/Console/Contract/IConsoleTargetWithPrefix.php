<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

/**
 * A console target that adds an optional prefix before each line of output
 *
 */
interface IConsoleTargetWithPrefix extends IConsoleTarget
{
    /**
     * Add a prefix before each line of output written to the target
     *
     * The prefix is applied to messages subsequently passed to
     * {@see IConsoleTarget::write()}.
     *
     * If `$prefix` is empty or `null`, a previously added prefix is cleared.
     */
    public function setPrefix(?string $prefix): void;
}
