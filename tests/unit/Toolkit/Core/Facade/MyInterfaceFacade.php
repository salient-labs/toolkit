<?php declare(strict_types=1);

namespace Salient\Tests\Core\Facade;

use Salient\Core\Facade\Facade;

/**
 * A facade for MyServiceInterface
 *
 * @method static mixed[] getArgs() Get arguments
 * @method static int getClones() Get the number of times the object has been cloned
 * @method static MyServiceInterface withArgs(mixed ...$args) Get an instance with the given arguments
 *
 * @extends Facade<MyServiceInterface>
 *
 * @generated
 */
final class MyInterfaceFacade extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            MyServiceInterface::class,
            [
                MyNonExistentClass::class,
                MyFacadeAwareInstanceClass::class,
            ],
        ];
    }
}
