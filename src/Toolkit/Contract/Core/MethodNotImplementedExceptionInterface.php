<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Throwable;

/**
 * @api
 */
interface MethodNotImplementedExceptionInterface extends Throwable
{
    /**
     * Get the name of the class that has not implemented the method
     *
     * @return class-string
     */
    public function getClass(): string;

    /**
     * Get the name of the method that has not been implemented
     */
    public function getMethod(): string;

    /**
     * Get the name of the class or interface from which the unimplemented
     * method is inherited
     *
     * @return class-string
     */
    public function getPrototypeClass(): string;
}
