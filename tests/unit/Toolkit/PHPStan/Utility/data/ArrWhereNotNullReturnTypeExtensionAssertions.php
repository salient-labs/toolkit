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
/** @var non-empty-array<int|float|string|bool> */
$i = [];
/** @var list<int|float|string|bool|Stringable|null> */
$j = [];
/** @var list<int|float|string|bool> */
$k = [];
/** @var array{foo:Stringable|null,bar:string|null,baz:int,qux?:float,quux:string} */
$l = [];
/** @var iterable<class-string,object> */
$m = [];
/** @var iterable<object|class-string,object|null> */
$n = [];
/** @var array<list<int|float|string|bool>> */
$o = [];
/** @var non-empty-array<list<int|float|string|bool>> */
$p = [];

assertType('*NEVER*', Arr::whereNotNull());
assertType('*NEVER*', Arr::whereNotNull($a));
assertType('*NEVER*', Arr::whereNotNull(...$b));
assertType('array{}', Arr::whereNotNull($b));
assertType("array{'', false}", Arr::whereNotNull($c));
assertType("array{0: '', 1: false, 3: 0, 4: 0.0, 5: '0'}", Arr::whereNotNull($d));
assertType("array{foo: '', bar: false}", Arr::whereNotNull($e));
assertType("array{quuux: '0', quux: 0.0, qux: 0, foo: '', bar: false}", Arr::whereNotNull($f));
assertType('array<bool|float|int|string>', Arr::whereNotNull($g));
assertType('array<bool|float|int|string>', Arr::whereNotNull($h));
assertType('non-empty-array<bool|float|int|string>', Arr::whereNotNull($i));
assertType('array<int<0, max>, bool|float|int|string|Stringable>', Arr::whereNotNull($j));
assertType('list<bool|float|int|string>', Arr::WHERENOTNULL($k));
assertType('array{foo?: Stringable, bar?: string, baz: int, qux?: float, quux: string}', Arr::whereNotNull($l));
assertType('array<class-string, object>', Arr::whereNotNull($m));
assertType('array<class-string|int, object>', Arr::whereNotNull($n));
assertType('array<class-string|int, object>', Arr::whereNotNull(...[$n]));
assertType('*NEVER*', Arr::whereNotNull(...$o));
assertType('list<bool|float|int|string>', Arr::whereNotNull(...$p));
/** @var iterable<object,string> $q */
assertType('array<int, string>', Arr::whereNotNull($q));
