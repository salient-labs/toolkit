<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Utility\Type;

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
/** @var array<string,array<class-string,string>> */
$e = [];
/** @var array{array{foo?:string,bar:int,baz?:string},array{foo?:int,bar?:string,baz:int}} */
$f = [];
$g = [
    'foo' => 0,
    'bar' => 1,
    'baz' => 2,
    [
        'foo' => null,
        'bar' => null,
        'baz' => [
            'FOO' => 3,
            'BAR' => 71,
        ],
    ],
];

assertType('array<mixed>', Arr::flatten());
assertType('array<mixed>', Arr::flatten('foo'));
assertType('array<mixed>', Arr::flatten([], 'foo'));
assertType('array<mixed>', Arr::flatten([], 'foo', -1));
assertType('array{}', Arr::flatten([]));
assertType('array{1}', Arr::flatten([1]));
assertType('array{2}', Arr::flatten([1 => 2]));
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
assertType('array{}', Arr::flatten([], -1, true));
assertType('array{1}', Arr::flatten([1], -1, true));
assertType('array{1: 2}', Arr::flatten([1 => 2], -1, true));
assertType('array{1: 2}', Arr::flatten([1 => 2], 0, true));
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
assertType("array{3: 'foo', bar: 'baz'}", Arr::flatten($d, 0, true));
assertType("array{3: 'foo', bar: 'baz'}", Arr::flatten($d, -1, true));
assertType('array<string>', Arr::flatten($e));
assertType('array<class-string, string>', Arr::flatten($e, -1, true));
assertType('array{0: int|string, 1: int|string, 2?: int|string, 3?: int|string, 4?: int|string, 5?: int}', Arr::flatten($f));
assertType('array{foo?: int|string, bar: int|string, baz: int}', Arr::flatten($f, -1, true));
assertType('array{0, 1, 2, null, null, 3, 71}', Arr::flatten($g));
assertType('array{0, 1, 2, null, null, array{FOO: 3, BAR: 71}}', Arr::flatten($g, 1));
assertType('array{foo: null, bar: null, baz: 2, FOO: 3, BAR: 71}', Arr::flatten($g, -1, true));
assertType('array{foo: 0, bar: 1, baz: 2, 0: array{foo: null, bar: null, baz: array{FOO: 3, BAR: 71}}}', Arr::flatten($g, 0, true));
assertType('array{foo: null, bar: null, baz: array{FOO: 3, BAR: 71}}', Arr::flatten($g, 1, true));
assertType('array{foo: null, bar: null, baz: 2, FOO: 3, BAR: 71}', Arr::flatten($g, 2, true));
