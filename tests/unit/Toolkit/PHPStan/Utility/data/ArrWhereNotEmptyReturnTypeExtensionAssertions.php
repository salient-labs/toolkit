<?php declare(strict_types=1);

use Salient\Utility\Arr;

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
$h = [];
/** @var non-empty-array<int|float> */
$i = [];
/** @var list<int|float|string|bool|Stringable|null> */
$j = [];
/** @var list<int|float|non-empty-string|true> */
$k = [];
/** @var array{foo:Stringable,bar:string,baz:int,qux?:float,quux:non-empty-string} */
$l = [];
/** @var iterable<class-string,object> */
$m = [];
/** @var iterable<object|class-string,object|string|null> */
$n = [];
/** @var array<list<int|float|non-empty-string|true>> */
$o = [];
/** @var non-empty-array<list<int|float|non-empty-string|true>> */
$p = [];

assertType('*NEVER*', Arr::whereNotEmpty());
assertType('*NEVER*', Arr::whereNotEmpty($a));
assertType('*NEVER*', Arr::whereNotEmpty(...$b));
assertType('array{}', Arr::whereNotEmpty($b));
assertType('array{}', Arr::whereNotEmpty($c));
assertType("array{3: 0, 4: 0.0, 5: '0'}", Arr::whereNotEmpty($d));
assertType('array{}', Arr::whereNotEmpty($e));
assertType("array{quuux: '0', quux: 0.0, qux: 0}", Arr::whereNotEmpty($f));
assertType('array<float|int|non-empty-string|true>', Arr::whereNotEmpty($g));
assertType('array<float|int|non-empty-string|true>', Arr::whereNotEmpty($h));
assertType('non-empty-array<float|int>', Arr::whereNotEmpty($i));
assertType('array<int<0, max>, float|int|non-empty-string|Stringable|true>', Arr::whereNotEmpty($j));
assertType('list<float|int|non-empty-string|true>', Arr::WHERENOTEMPTY($k));
assertType('array{foo?: Stringable, bar?: non-empty-string, baz: int, qux?: float, quux: non-empty-string}', Arr::whereNotEmpty($l));
assertType('array<class-string, object>', Arr::whereNotEmpty($m));
assertType('array<class-string|int, object|non-empty-string>', Arr::whereNotEmpty($n));
assertType('array<class-string|int, object|non-empty-string>', Arr::whereNotEmpty(...[$n]));
assertType('*NEVER*', Arr::whereNotEmpty(...$o));
assertType('list<float|int|non-empty-string|true>', Arr::whereNotEmpty(...$p));
/** @var iterable<object,string> $q */
assertType('array<int, non-empty-string>', Arr::whereNotEmpty($q));
