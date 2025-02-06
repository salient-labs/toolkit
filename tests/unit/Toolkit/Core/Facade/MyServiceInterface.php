<?php declare(strict_types=1);

namespace Salient\Tests\Core\Facade;

use Salient\Contract\Core\Instantiable;

interface MyServiceInterface extends Instantiable
{
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

    /**
     * Get an instance with the given arguments
     *
     * @param mixed ...$args
     * @return static
     */
    public function withArgs(...$args);
}
