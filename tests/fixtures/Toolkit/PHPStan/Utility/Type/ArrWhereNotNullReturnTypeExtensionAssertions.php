<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Utility\Type;

use Salient\Utility\Arr;
use Stringable;

use function PHPStan\Testing\assertType;

$a = 'foo';
$b = [];
$c = ['', false, null];
$d = [...$c, 0, 0.0, '0'];
$e = ['foo' => '', 'bar' => false, 'baz' => null];
$f = $e + ['quuux' => '0', 'quux' => 0.0, 'qux' => 0];
/** @var array<int|float|string|bool|null> */
$g = [];
/** @var non-empty-array<int|float|string|bool|null> */
$k = [];
/** @var non-empty-array<int|float|string|bool> */
$l = [];
/** @var list<int|float|string|bool|Stringable|null> */
$h = [];
/** @var list<int|float|string|bool> */
$i = [];
/** @var array{foo:Stringable|null,bar:string|null,baz:int,qux?:float,quux:string} */
$j = [];

assertType('array<mixed>', Arr::whereNotNull());
assertType('array<mixed>', Arr::whereNotNull($a));
assertType('array{}', Arr::whereNotNull($b));
assertType("array{'', false}", Arr::whereNotNull($c));
assertType("array{0: '', 1: false, 3: 0, 4: 0.0, 5: '0'}", Arr::whereNotNull($d));
assertType("array{foo: '', bar: false}", Arr::whereNotNull($e));
assertType("array{quuux: '0', quux: 0.0, qux: 0, foo: '', bar: false}", Arr::whereNotNull($f));
assertType('array<bool|float|int|string>', Arr::whereNotNull($g));
assertType('array<bool|float|int|string>', Arr::whereNotNull($k));
assertType('non-empty-array<bool|float|int|string>', Arr::whereNotNull($l));
assertType('array<int<0, max>, bool|float|int|string|Stringable>', Arr::whereNotNull($h));
assertType('list<bool|float|int|string>', Arr::whereNotNull($i));
assertType('array{foo?: Stringable, bar?: string, baz: int, qux?: float, quux: string}', Arr::whereNotNull($j));
