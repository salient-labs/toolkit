<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * @template T of IContainer
 */
interface HasContainer
{
    /**
     * Get the object's service container
     *
     * @return T
     */
    public function app(): IContainer;

    /**
     * Get the object's service container
     *
     * @return T
     */
    public function container(): IContainer;
}
