<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\FacadeAwareInterface;
use Lkrms\Contract\FacadeInterface;
use LogicException;

/**
 * Implements FacadeAwareInterface
 *
 * @see FacadeAwareInterface
 */
trait HasFacade
{
    /**
     * @var class-string<FacadeInterface<static>>|null
     */
    protected ?string $Facade = null;

    private ?self $InstanceWithoutFacade = null;

    private ?self $InstanceWithFacade = null;

    /**
     * @param class-string<FacadeInterface<static>> $facade
     * @return static
     */
    final public function withFacade(string $facade)
    {
        if ($this->Facade !== null) {
            throw new LogicException(sprintf(
                '%s already has facade %s',
                static::class,
                $this->Facade,
            ));
        }

        // Revert to `$this->InstanceWithFacade` if `$facade` matches and
        // `$this` hasn't been cloned in the meantime
        if (
            $this->InstanceWithFacade &&
            $this->InstanceWithFacade->Facade === $facade &&
            $this->InstanceWithoutFacade === $this
        ) {
            return $this->InstanceWithFacade;
        }

        $instance = clone $this;
        $instance->Facade = $facade;
        $instance->InstanceWithoutFacade = $this;
        $instance->InstanceWithFacade = $instance;
        return $instance;
    }

    /**
     * @param class-string<FacadeInterface<static>> $facade
     * @return static
     */
    final public function withoutFacade(string $facade, bool $unloading)
    {
        if (
            $this->Facade === null && (
                !$unloading || (
                    $this->InstanceWithoutFacade === null &&
                    $this->InstanceWithFacade === null
                )
            )
        ) {
            return $this;
        }

        if ($this->Facade !== $facade) {
            throw new LogicException(sprintf(
                '%s has facade %s, not %s',
                static::class,
                $this->Facade,
                $facade,
            ));
        }

        // Revert to `$this->InstanceWithoutFacade` if `$this` hasn't been
        // cloned in the meantime
        if ($this->InstanceWithFacade === $this && !$unloading) {
            return $this->InstanceWithoutFacade;
        }

        $instance = clone $this;
        $instance->Facade = null;

        if (!$unloading) {
            $instance->InstanceWithoutFacade = $instance;
            $instance->InstanceWithFacade = $this;
        }

        return $instance;
    }
}
