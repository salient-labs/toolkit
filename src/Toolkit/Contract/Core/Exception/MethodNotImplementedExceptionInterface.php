<?php declare(strict_types=1);

namespace Salient\Contract\Core\Exception;

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
     * Get the name of the method that is not implemented
     */
    public function getMethod(): string;

    /**
     * Get the name of the class or interface where the unimplemented method is
     * defined
     *
     * @return class-string
     */
    public function getPrototypeClass(): string;
}
