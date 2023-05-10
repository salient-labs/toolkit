<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Records the names of its mutators
 *
 */
trait IsMutable
{
    /**
     * @var string[]
     */
    private $_Mutators;

    /**
     * Called immediately after the object is modified
     *
     * If changes are applied to a clone of the object, `mutate()` is called on
     * the clone.
     *
     * Methods that change the object's state should identify themselves to
     * {@see IsMutable::mutate()} immediately before returning, e.g.:
     *
     * ```php
     * public function truncate()
     * {
     *     $clone = clone $this;
     *     $clone->Items = [];
     *
     *     return $clone->mutate(__FUNCTION__, static::class);
     * }
     * ```
     *
     * @param class-string $class
     * @return $this
     */
    final public function mutate(string $mutator, string $class)
    {
        $this->_Mutators[] = $class . '::' . $mutator;

        return $this;
    }

    /**
     * Get a list of methods that have modified the object
     *
     * @param bool $clear If `true` (the default), empty the object's mutator
     * list and clear its modified status.
     * @return string[]
     */
    final protected function getMutators(bool $clear = true): array
    {
        if (!$clear) {
            return $this->_Mutators;
        }
        $mutators = $this->_Mutators;
        $this->_Mutators = [];

        return $mutators;
    }

    /**
     * Empty the object's mutator list and clear its modified status
     *
     * @return $this
     */
    final protected function clearMutators()
    {
        $this->_Mutators = [];

        return $this;
    }

    /**
     * True if the object has been modified by a mutator
     *
     */
    final public function isMutant(): bool
    {
        return (bool) $this->_Mutators;
    }
}
