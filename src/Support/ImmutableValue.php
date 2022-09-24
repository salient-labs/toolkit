<?php

declare(strict_types=1);

namespace Lkrms\Support;

/**
 * Wrap a value in an object so it can be passed around like one
 *
 */
final class ImmutableValue
{
    private $Value;

    public function __construct($value)
    {
        $this->Value = $value;
    }

    public function __toString(): string
    {
        return (string)$this->Value;
    }

    public function __invoke()
    {
        return $this->Value;
    }

    public static function new($value): self
    {
        return new self($value);
    }

    public function get()
    {
        return $this->Value;
    }

}
