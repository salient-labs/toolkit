<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Salient\Core\Facade;

/**
 * A facade for MyServiceInterface
 *
 * @method static mixed[] getArgs() Get arguments
 * @method static int getClones() Get the number of times the object has been cloned
 * @method static string getMethod() Get __METHOD__
 *
 * @extends Facade<MyServiceInterface>
 *
 * @generated
 */
final class MyBrokenFacade extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService()
    {
        return [
            MyServiceInterface::class => MyNonExistentClass::class,
        ];
    }
}
