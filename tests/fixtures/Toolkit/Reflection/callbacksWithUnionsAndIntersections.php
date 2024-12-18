<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

use ArrayAccess;
use Countable;

return [
    [
        [Countable::class, ArrayAccess::class],
        fn(Countable|ArrayAccess $union) => null,
    ],
    [
        [[Countable::class, ArrayAccess::class]],
        fn(Countable&ArrayAccess $intersection) => null,
    ],
];
