<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Facade\FacadeInterface;
use Salient\Contract\Core\Instantiable;
use LogicException;

/**
 * Returns modified instances for use with and without a facade
 *
 * @api
 *
 * @template TService of Instantiable
 *
 * @phpstan-require-implements FacadeAwareInterface
 */
trait FacadeAwareInstanceTrait
{
    /** @var class-string<FacadeInterface<TService>>|null */
    protected ?string $Facade = null;
    /** @var static|null */
    private ?self $InstanceWithoutFacade = null;
    /** @var static|null */
    private ?self $InstanceWithFacade = null;
    private bool $HasTentativeFacade = false;

    /**
     * @param class-string<FacadeInterface<TService>> $facade
     */
    final public function withFacade(string $facade)
    {
        if ($this->Facade === $facade) {
            return $this;
        }

        if ($this->Facade !== null) {
            throw new LogicException(sprintf(
                '%s already has facade %s',
                static::class,
                $this->Facade,
            ));
        }

        // Reuse `$this->InstanceWithFacade` if `$facade` matches and `$this`
        // hasn't been cloned in the meantime
        if (
            $this->InstanceWithoutFacade === $this
            && $this->InstanceWithFacade
            && $this->InstanceWithFacade->Facade === $facade
        ) {
            return $this->InstanceWithFacade;
        }

        $this->HasTentativeFacade = true;

        $instance = clone $this;
        $instance->Facade = $facade;
        $instance->InstanceWithoutFacade = $this;
        $instance->InstanceWithFacade = $instance;
        $instance->HasTentativeFacade = false;

        $this->InstanceWithoutFacade = $this;
        $this->InstanceWithFacade = $instance;
        $this->HasTentativeFacade = false;

        return $instance;
    }

    /**
     * @param class-string<FacadeInterface<TService>> $facade
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

        // Reuse `$this->InstanceWithoutFacade` if `$this` hasn't been cloned in
        // the meantime
        if (
            $this->InstanceWithFacade === $this
            && $this->InstanceWithoutFacade
            && !$unloading
        ) {
            return $this->InstanceWithoutFacade;
        }

        $this->HasTentativeFacade = true;

        $instance = clone $this;
        $instance->Facade = null;
        $instance->HasTentativeFacade = false;

        if ($unloading) {
            $instance->InstanceWithoutFacade = null;
            $instance->InstanceWithFacade = null;
        } else {
            $instance->InstanceWithoutFacade = $instance;
            $instance->InstanceWithFacade = $this;

            $this->InstanceWithoutFacade = $instance;
            $this->InstanceWithFacade = $this;
        }

        $this->HasTentativeFacade = false;

        return $instance;
    }

    /**
     * If the object is not the underlying instance of the facade it's being
     * used with, update the facade
     *
     * This method can be used to keep a facade up-to-date with an immutable
     * underlying instance, e.g. by calling it from `__clone()`.
     */
    final protected function updateFacade(): void
    {
        if (
            $this->Facade !== null
            && !$this->HasTentativeFacade
            && $this->Facade::isLoaded()
        ) {
            $instance = $this->Facade::getInstance();
            if (
                !$instance instanceof static
                || $instance->InstanceWithFacade !== $this
            ) {
                $this->Facade::swap($this);
            }
        }
    }
}
