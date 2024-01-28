<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\FacadeAwareInterface;
use Lkrms\Contract\FacadeInterface;
use Lkrms\Utility\Get;
use LogicException;

/**
 * Implements FacadeAwareInterface by maintaining a list of facades the instance
 * is being used by
 *
 * @see FacadeAwareInterface
 *
 * @template TFacade of FacadeInterface
 */
trait UnloadsFacades
{
    /**
     * Normalised FQCN => given FQCN
     *
     * @var array<class-string<TFacade>,class-string<TFacade>>
     */
    private $Facades = [];

    /**
     * @param class-string<TFacade> $facade
     * @return static
     */
    final public function withFacade(string $facade)
    {
        $this->Facades[Get::fqcn($facade)] = $facade;
        return $this;
    }

    /**
     * @param class-string<TFacade> $facade
     * @return static
     */
    final public function withoutFacade(string $facade, bool $unloading)
    {
        if ($unloading) {
            unset($this->Facades[Get::fqcn($facade)]);
        }
        return $this;
    }

    /**
     * Unload any facades where the object is the underlying instance
     */
    final protected function unloadFacades(): void
    {
        if (!$this->Facades) {
            return;
        }

        foreach ($this->Facades as $fqcn => $facade) {
            if (!$facade::isLoaded() || $facade::getInstance() !== $this) {
                unset($this->Facades[$fqcn]);
                continue;
            }

            $facade::unload();
        }

        if (!$this->Facades) {
            return;
        }

        throw new LogicException(sprintf(
            'Underlying %s not unloaded: %s',
            static::class,
            implode(' ', $this->Facades),
        ));
    }
}
