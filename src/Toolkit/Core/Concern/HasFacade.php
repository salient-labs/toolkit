<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Core\Contract\FacadeAwareInterface;
use Salient\Core\Contract\FacadeInterface;
use LogicException;

/**
 * Implements FacadeAwareInterface by returning modified instances for use with
 * and without a facade
 *
 * @see FacadeAwareInterface
 *
 * @api
 *
 * @template TFacade of FacadeInterface
 */
trait HasFacade
{
    /**
     * @var class-string<TFacade>|null
     */
    protected ?string $Facade = null;

    /**
     * @var static|null
     */
    private ?self $InstanceWithoutFacade = null;

    /**
     * @var static|null
     */
    private ?self $InstanceWithFacade = null;

    /**
     * @param class-string<TFacade> $facade
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
     * @param class-string<TFacade> $facade
     * @return static
     */
    final public function withoutFacade(string $facade, bool $unloading)
    {
        if ($this->Facade !== $facade) {
            throw new LogicException(sprintf(
                '%s does not have facade %s',
                static::class,
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

        // Keep a copy of the cloned instance for future reuse if the facade is
        // not being unloaded
        if ($unloading) {
            $instance->InstanceWithoutFacade = null;
            $instance->InstanceWithFacade = null;
        } else {
            $instance->InstanceWithoutFacade = $instance;
            $instance->InstanceWithFacade = $this;
        }

        return $instance;
    }
}
