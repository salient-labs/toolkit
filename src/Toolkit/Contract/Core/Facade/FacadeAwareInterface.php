<?php declare(strict_types=1);

namespace Salient\Contract\Core\Facade;

use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;

/**
 * @api
 *
 * @template TService of Instantiable
 */
interface FacadeAwareInterface
{
    /**
     * Get an instance to use behind the given facade
     *
     * @param class-string<FacadeInterface<TService>> $facade
     * @return static
     */
    public function withFacade(string $facade);

    /**
     * Get an instance to use independently of the given facade
     *
     * If `$unloading` is `true`:
     *
     * - the facade is being unloaded
     * - the instance returned by this method will be removed from the facade
     * - if the instance also implements {@see Unloadable}, the facade will call
     *   its {@see Unloadable::unload()} method before it is removed
     *
     * @param class-string<FacadeInterface<TService>> $facade
     * @return static
     */
    public function withoutFacade(string $facade, bool $unloading);
}
