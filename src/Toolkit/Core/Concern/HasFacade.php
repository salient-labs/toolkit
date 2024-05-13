<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Core\Exception\LogicException;

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
    /** @var class-string<TFacade>|null */
    protected ?string $Facade = null;
    /** @var static|null */
    private ?self $InstanceWithoutFacade = null;
    /** @var static|null */
    private ?self $InstanceWithFacade = null;
    private bool $HasTentativeFacade = false;

    /**
     * @param class-string<TFacade> $facade
     * @return static
     */
    final public function withFacade(string $facade)
    {
        if ($this->Facade === $facade) {
            return $this;
        }

        if ($this->Facade !== null) {
            // @codeCoverageIgnoreStart
            throw new LogicException(sprintf(
                '%s already has facade %s',
                static::class,
                $this->Facade,
            ));
            // @codeCoverageIgnoreEnd
        }

        // Revert to `$this->InstanceWithFacade` if `$facade` matches and
        // `$this` hasn't been cloned in the meantime
        if (
            $this->InstanceWithoutFacade === $this
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
     * @param class-string<TFacade> $facade
     * @return static
     */
    final public function withoutFacade(string $facade, bool $unloading)
    {
        if ($this->Facade !== $facade) {
            // @codeCoverageIgnoreStart
            throw new LogicException(sprintf(
                '%s does not have facade %s',
                static::class,
                $facade,
            ));
            // @codeCoverageIgnoreEnd
        }

        // Revert to `$this->InstanceWithoutFacade` if `$this` hasn't been
        // cloned in the meantime
        if ($this->InstanceWithFacade === $this && !$unloading) {
            return $this->InstanceWithoutFacade;
        }

        $this->HasTentativeFacade = true;

        $instance = clone $this;
        $instance->Facade = null;
        $instance->HasTentativeFacade = false;

        // Keep a copy of the cloned instance for future reuse if the facade is
        // not being unloaded
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
        if ($this->Facade === null || $this->HasTentativeFacade) {
            return;
        }

        if (!$this->Facade::isLoaded()) {
            // @codeCoverageIgnoreStart
            throw new LogicException(sprintf(
                '%s has unloaded facade %s',
                static::class,
                $this->Facade,
            ));
            // @codeCoverageIgnoreEnd
        }

        $instance = $this->Facade::getInstance();
        if ($instance instanceof self && $instance->InstanceWithFacade === $this) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $this->Facade::swap($this);
    }
}
