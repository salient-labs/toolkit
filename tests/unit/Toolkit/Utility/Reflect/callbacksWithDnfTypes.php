<?php declare(strict_types=1);

namespace Salient\Tests\Utility\Reflect;

use ArrayAccess;
use Countable;

return [
    [
        [MyClass::class, [Countable::class, ArrayAccess::class], 'string'],
        fn(string|MyClass|(Countable&ArrayAccess) $dnf) => null,
    ],
    [
        [MyClass::class, [Countable::class, ArrayAccess::class]],
        fn(string|MyClass|(Countable&ArrayAccess) $dnf) => null,
        true,
    ],
    [
        [[MyClass::class, Countable::class], [MyClass::class, ArrayAccess::class]],
        fn((MyClass&Countable)|(MyClass&ArrayAccess) &$dnfByRef) => null,
    ],
];
