<?php declare(strict_types=1);

use Salient\Utility\Arr;

use function PHPStan\Testing\assertType;

$a = [Reflector::class, ReflectionFunction::class, ReflectionObject::class];
$b = [ReflectionFunction::class, ReflectionMethod::class];
$c = [ReflectionClass::class, ReflectionObject::class];
/** @var class-string<Reflector>[] */
$d = [];
/** @var class-string<ReflectionFunctionAbstract>[] */
$e = [];
/** @var class-string<ReflectionClass<object>>[] */
$f = [];
/** @var array{foo?:string,bar:int,baz?:string} */
$g = [];
/** @var array{foo?:string,bar:int,0?:string} */
$h = [];
/** @var array{Stringable|string,int|float|null,2:bool,true} */
$i = [];
/** @var array{Stringable|string,int|float,2?:bool,true} */
$j = [];
/** @var non-empty-array<mixed> */
$k = [];
/** @var mixed[] */
$l = [];
/** @var list<mixed> */
$m = [];

assertType('*NEVER*', Arr::extend());
assertType('*NEVER*', Arr::extend('foo'));
assertType("array{'a', 'a', 'd', 'd', 'b', 'b', 'c', 'c'}", Arr::extend(['a', 'a', 'd', 'd'], 'a', 'a', 'a', 'b', 'b', 'c', 'c'));
assertType("array{foo: 'a', bar: 'd', 0: 'b', 1: 'c'}", Arr::extend(['foo' => 'a', 'bar' => 'd'], 'a', 'b', 'c'));
assertType("array{'Reflector', 'ReflectionFunction', 'ReflectionObject', 'ReflectionMethod', 'ReflectionClass'}", Arr::extend($a, ...$b, ...$c));
assertType("array{'ReflectionFunction', 'ReflectionMethod'}", Arr::extend($b));
assertType("array{'ReflectionFunction', 'ReflectionMethod', 'ReflectionClass', 'ReflectionObject'}", Arr::extend($b, ...$c));
assertType(sprintf('array<class-string<%s>>', Reflector::class), Arr::extend($d, ...$e, ...$f));
assertType(sprintf('array<class-string<%s>>', ReflectionFunctionAbstract::class), Arr::extend($e));
assertType(sprintf('array<class-string<%s<object>>|class-string<%s>>', ReflectionClass::class, ReflectionFunctionAbstract::class), Arr::extend($e, ...$f));
assertType(sprintf('non-empty-list<class-string<%s>>', Reflector::class), Arr::extend($a, ...$b, ...$c, ...$d, ...$e, ...$f));
assertType('array{foo?: string, bar: int, baz?: string, 0: string|Stringable, 1: float|int|null, 2: bool, 3: true}', Arr::extend($g, ...$i));
assertType('array{foo?: string, bar: int, baz?: string, 0: string|Stringable, 1: float|int, 2: bool, 3?: true}', Arr::extend($g, ...$j));
assertType('array{foo?: string, bar: int, 0: string|Stringable, 1: float|int|string|Stringable|null, 2: bool|float|int|null, 3: bool, 4?: true}', Arr::extend($h, ...$i));
assertType('array{foo?: string, bar: int, 0: string|Stringable, 1: float|int|string|Stringable, 2: bool|float|int, 3?: bool, 4?: true}', Arr::extend($h, ...$j));
assertType('non-empty-array<mixed>', Arr::extend($k));
assertType('non-empty-array<mixed>', Arr::extend($k, 71));
assertType('array<mixed>', Arr::extend($l));
assertType('non-empty-array<mixed>', Arr::extend($l, 71));
assertType('non-empty-array<mixed>', Arr::extend($l, ...$k));
assertType('array<mixed>', Arr::extend($l, ...$l));
assertType('non-empty-array<mixed>', Arr::extend($l, $l));
assertType('list<mixed>', Arr::extend($m));
assertType('non-empty-list<mixed>', Arr::extend($m, 71));
