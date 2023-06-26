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
     * @param bool $suppress If `true`, disable delegation of method calls to
     * `$delegate` until {@see HasConditionalDelegate::elseIf()} or
     * {@see HasConditionalDelegate::else()} are called.
     */
    public static function withDelegate($delegate, bool $suppress = false);

    /**
     * @return $this
     */
    public function __call(string $name, array $arguments);

    /**
     * Conditionally enable or disable delegation of method calls to the
     * delegate
     *
     * @param (callable(TDelegate): bool)|bool $condition
     * @return $this
     */
    public function elseIf($condition);

    /**
     * Enable or disable delegation of method calls to the delegate
     *
     * @return $this
     */
    public function else();

    /**
     * Terminate conditional delegation of method calls and property actions
     *
     * @return TDelegate
     */
    public function endIf();
}
