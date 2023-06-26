<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\HasConditionalDelegate;
use Lkrms\Contract\IImmutable;
use LogicException;

/**
 * @template TDelegate of object
 * @implements HasConditionalDelegate<TDelegate>
 * @mixin TDelegate
 */
final class FluentDelegator implements HasConditionalDelegate
{
    /**
     * @var TDelegate
     */
    private $Delegate;

    /**
     * @var class-string<TDelegate>
     */
    private $DelegateClass;

    private bool $DelegateIsImmutable;

    private bool $Suppress;

    private bool $ConditionMet;

    private bool $ReceivedElse = false;

    /**
     * @param TDelegate $delegate
     */
    private function __construct($delegate, bool $suppress = false)
    {
        $this->Delegate = $delegate;
        $this->DelegateClass = get_class($delegate);
        $this->DelegateIsImmutable = $delegate instanceof IImmutable;
        $this->Suppress = $suppress;
        $this->ConditionMet = !$suppress;
    }

    public static function withDelegate($delegate, bool $suppress = false)
    {
        return new self($delegate, $suppress);
    }

    public function __call(string $name, array $arguments)
    {
        if ($this->Suppress) {
            return $this;
        }
        $result = $this->Delegate->{$name}(...$arguments);
        if ($this->DelegateIsImmutable) {
            if (!is_object($result) || get_class($result) !== $this->DelegateClass) {
                throw new LogicException(sprintf(
                    'Return value of %s::%s() must be of type $this, %s returned',
                    $this->DelegateClass,
                    $name,
                    is_object($result) ? get_class($result) : gettype($result)
                ));
            }
        } elseif ($result !== $this->Delegate) {
            throw new LogicException(sprintf(
                'Return value of %s::%s() must be $this, %s returned',
                $this->DelegateClass,
                $name,
                is_object($result) ? get_class($result) : gettype($result)
            ));
        }
        $this->Delegate = $result;
        return $this;
    }

    public function elseIf($condition)
    {
        if ($this->ReceivedElse) {
            throw new LogicException('else() cannot be called before elseIf()');
        }
        $this->Suppress = true;
        if ($this->ConditionMet) {
            return $this;
        }
        if (is_callable($condition)) {
            $condition = $condition($this->Delegate);
        }
        if ($condition) {
            $this->Suppress = false;
            $this->ConditionMet = true;
        }
        return $this;
    }

    public function else()
    {
        if ($this->ReceivedElse) {
            throw new LogicException('else() cannot be called multiple times');
        }
        $this->ReceivedElse = true;
        if ($this->ConditionMet) {
            $this->Suppress = true;
            return $this;
        }
        $this->Suppress = false;
        $this->ConditionMet = true;
        return $this;
    }

    public function endIf()
    {
        return $this->Delegate;
    }

    public function getDelegate()
    {
        return $this->Delegate;
    }

    public function __set(string $name, $value): void
    {
        if ($this->Suppress) {
            return;
        }
        $this->Delegate->{$name} = $value;
    }

    public function __get(string $name)
    {
        return $this->Delegate->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->Delegate->{$name});
    }

    public function __unset(string $name): void
    {
        if ($this->Suppress) {
            return;
        }
        $this->Delegate->{$name} = null;
    }
}
