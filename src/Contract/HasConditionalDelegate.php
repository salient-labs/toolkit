<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Method calls and property actions are conditionally delegated to a receiving
 * object
 *
 * @template TDelegate of object
 * @extends HasDelegate<TDelegate>
 */
interface HasConditionalDelegate extends HasDelegate
{
    /**
     * @param bool $suppress If `true`, method calls will not be delegated to
     * `$object`, and property actions may raise exceptions.
     */
    public static function withDelegate(object $delegate, bool $suppress = false);

    /**
     * @return $this
     */
    public function __call(string $name, array $arguments);

    /**
     * Terminate conditional delegation of method calls and property actions
     *
     * @return TDelegate
     */
    public function endIf();
}
