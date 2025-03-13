<?php declare(strict_types=1);

namespace Salient\Contract\Console\Target;

/**
 * A console output target that applies an optional prefix to each line of
 * output
 */
interface HasPrefix extends TargetInterface
{
    /**
     * Set or unset the prefix applied to each line of output
     *
     * @return $this
     */
    public function setPrefix(?string $prefix);

    /**
     * Get the prefix applied to each line of output
     */
    public function getPrefix(): ?string;
}
