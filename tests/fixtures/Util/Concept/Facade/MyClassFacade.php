<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Salient\Core\Facade;

/**
 * A facade for MyServiceClass
 *
 * @method static mixed[] getArgs() Call MyServiceClass::getArgs() on the facade's underlying instance, loading it if necessary
 * @method static string getClass() Call MyServiceClass::getClass() on the facade's underlying instance, loading it if necessary
 * @method static int getClones() Call MyServiceClass::getClones() on the facade's underlying instance, loading it if necessary
 * @method static static[] getUnloaded() Call MyServiceClass::getUnloaded() on the facade's underlying instance, loading it if necessary
 * @method static void reset() Call MyServiceClass::reset() on the facade's underlying instance, loading it if necessary
 *
 * @extends Facade<MyServiceClass>
 *
 * @generated
 */
final class MyClassFacade extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService()
    {
        return MyServiceClass::class;
    }
}
