<?php declare(strict_types=1);

namespace Salient\Contract\Console\Target;

/**
 * @api
 */
interface HasPrefix extends TargetInterface
{
    /**
     * Set or unset the prefix applied to each line of output written to the
     * target
     *
     * @return $this
     */
    public function setPrefix(?string $prefix);

    /**
     * Get the prefix applied to each line of output written to the target
     */
    public function getPrefix(): ?string;
}
