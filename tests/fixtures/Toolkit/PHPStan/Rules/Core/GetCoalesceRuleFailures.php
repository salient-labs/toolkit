<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Rules\Core;

use Salient\Core\Utility\Get;

$a = 2;
$b = 3;
$c = [3, 2, null];

$empty = Get::coalesce();
$null = Get::coalesce(null);
$nulls = Get::coalesce(null, null);
$unpacked1 = Get::coalesce(...[null, null]);
$scalar = Get::coalesce($a, null);
$scalars = Get::coalesce($b, $a, null);
$array = Get::coalesce($c, null);
$unpacked2 = Get::coalesce(...$c);
$unpacked3 = Get::coalesce(...[...$c, null]);
