<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Implemented by classes that need to know when they are used behind a facade
 *
 * @see FacadeInterface
 *
 * @api
 *
 * @template TFacade of FacadeInterface
 */
interface FacadeAwareInterface
{
    /**
     * Get an instance to use behind a given facade
     *
     * @param class-string<TFacade> $facade
     * @return static
     */
    public function withFacade(string $facade);

    /**
     * Get an instance to use without a given facade
     *
     * If `$unloading` is `true`:
     *
     * - the facade is being unloaded
     * - the instance returned by this method will be removed from the facade
     * - if the instance also implements {@see Unloadable}, the facade will call
     *   its {@see Unloadable::unload()} method before it is removed
     *
     * @param class-string<TFacade> $facade
     * @return static
     */
    public function withoutFacade(string $facade, bool $unloading);
}
