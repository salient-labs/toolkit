<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type\Core;

use Salient\Utility\Arr;
use stdClass;

use function PHPStan\Testing\assertType;

/** @var array<string,array<string,int[][]>> */
$a = [];
/** @var array<string,array<int,stdClass[]|string[]|int[]|bool|stdClass>> */
$b = [];
/** @var array<string,array<int,array{3:"foo",bar:"baz"}>> */
$c = [];
/** @var array{3:"foo",bar:"baz"} */
$d = [];

assertType('array', Arr::flatten());
assertType('array', Arr::flatten('foo'));
assertType('array', Arr::flatten([], 'foo'));
assertType('array{1}', Arr::flatten([1]));
assertType('array{1}', Arr::flatten([[1]]));
assertType('array{1}', Arr::flatten([[[1]]]));
assertType('array{array{array{1}}}', Arr::flatten([[[1]]], 0));
assertType('array{array{1}}', Arr::flatten([[[1]]], 1));
assertType('array{1}', Arr::flatten([[[1]]], 2));
assertType('array{1}', Arr::flatten([[[1]]], 3));
assertType('array{array{1}}', Arr::flatten([[[[1]]]], 2));
assertType("array{1, 'foo', null, true}", Arr::flatten([1, 'foo', [null, true]]));
assertType("array{1, 'foo', null, true}", Arr::flatten([[1, 'foo', [null, true]]]));
assertType("array{1, 'foo', null, true}", Arr::flatten([[[1, 'foo', [null, true]]]]));
assertType("array{array{array{1, 'foo', array{null, true}}}}", Arr::flatten([[[1, 'foo', [null, true]]]], 0));
assertType("array{array{1, 'foo', array{null, true}}}", Arr::flatten([[[1, 'foo', [null, true]]]], 1));
assertType("array{1, 'foo', array{null, true}}", Arr::flatten([[[1, 'foo', [null, true]]]], 2));
assertType("array{1, 'foo', null, true}", Arr::flatten([[[1, 'foo', [null, true]]]], 3));
assertType("array{array{1, 'foo', array{null, true}}}", Arr::flatten([[[[1, 'foo', [null, true]]]]], 2));
assertType('array{}', Arr::flatten([[[[[]]]]]));
assertType('array{array{array{array{}}}}', Arr::flatten([[[[[]]]]], 1));
assertType('array{array{}}', Arr::flatten([[[[[]]]]], 3));
assertType('array<array<string, array<array<int>>>>', Arr::flatten($a, 0));
assertType('array<array<array<int>>>', Arr::flatten($a, 1));
assertType('array<array<int>>', Arr::flatten($a, 2));
assertType('array<int>', Arr::flatten($a));
assertType('array{array<string, array<string, array<array<int>>>>}', Arr::flatten([$a], 0));
assertType('array<array<string, array<array<int>>>>', Arr::flatten([$a], 1));
assertType('array<array<array<int>>>', Arr::flatten([$a], 2));
assertType('array<array<int>>', Arr::flatten([$a], 3));
assertType('array<int>', Arr::flatten([$a]));
assertType('array<array<int, array<int|stdClass|string>|bool|stdClass>>', Arr::flatten($b, 0));
assertType('array<array<int|stdClass|string>|bool|stdClass>', Arr::flatten($b, 1));
assertType('array<bool|int|stdClass|string>', Arr::flatten($b));
assertType("array<array<int, array{3: 'foo', bar: 'baz'}>>", Arr::flatten($c, 0));
assertType("array<array{3: 'foo', bar: 'baz'}>", Arr::flatten($c, 1));
assertType("array<'baz'|'foo'>", Arr::flatten($c));
assertType("array{array<string, array<int, array{3: 'foo', bar: 'baz'}>>}", Arr::flatten([$c], 0));
assertType("array<'baz'|'foo'>", Arr::flatten([$c]));
assertType("array{'foo', 'baz'}", Arr::flatten($d, 0));
assertType("array{'foo', 'baz'}", Arr::flatten($d));
