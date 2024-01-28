<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

interface MyServiceInterface
{
    /**
     * Get __METHOD__
     */
    public function getMethod(): string;

    /**
     * Get arguments
     *
     * @return mixed[]
     */
    public function getArgs(): array;

    /**
     * Get the number of times the object has been cloned
     */
    public function getClones(): int;
}
