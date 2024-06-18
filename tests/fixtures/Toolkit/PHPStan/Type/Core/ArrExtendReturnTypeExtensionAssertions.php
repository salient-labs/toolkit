<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type\Core;

use Salient\Utility\Arr;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;
use Reflector;
use Stringable;

use function PHPStan\Testing\assertType;

$a = [Reflector::class, ReflectionFunction::class, ReflectionObject::class];
$b = [ReflectionFunction::class, ReflectionMethod::class];
$c = [ReflectionClass::class, ReflectionObject::class];
/** @var class-string<Reflector>[] */
$h = [];
/** @var class-string<ReflectionFunctionAbstract>[] */
$i = [];
/** @var class-string<ReflectionClass<object>>[] */
$j = [];
/** @var array{foo?:string,bar:int,baz?:string} */
$d = [];
/** @var array{foo?:string,bar:int,0?:string} */
$e = [];
/** @var array{Stringable|string,int|float|null,2:bool,true} */
$f = [];
/** @var array{Stringable|string,int|float,2?:bool,true} */
$g = [];
/** @var non-empty-array<mixed> */
$k = [];
/** @var mixed[] */
$l = [];
/** @var list<mixed> */
$m = [];

assertType('array', Arr::extend());
assertType('array', Arr::extend('foo'));
assertType("array{'a', 'a', 'd', 'd', 'b', 'b', 'c', 'c'}", Arr::extend(['a', 'a', 'd', 'd'], 'a', 'a', 'a', 'b', 'b', 'c', 'c'));
assertType("array{foo: 'a', bar: 'd', 0: 'b', 1: 'c'}", Arr::extend(['foo' => 'a', 'bar' => 'd'], 'a', 'b', 'c'));
assertType("array{'Reflector', 'ReflectionFunction', 'ReflectionObject', 'ReflectionMethod', 'ReflectionClass'}", Arr::extend($a, ...$b, ...$c));
assertType("array{'ReflectionFunction', 'ReflectionMethod'}", Arr::extend($b));
assertType("array{'ReflectionFunction', 'ReflectionMethod', 'ReflectionClass', 'ReflectionObject'}", Arr::extend($b, ...$c));
assertType(sprintf('array<class-string<%s>>', Reflector::class), Arr::extend($h, ...$i, ...$j));
assertType(sprintf('array<class-string<%s>>', ReflectionFunctionAbstract::class), Arr::extend($i));
assertType(sprintf('array<class-string<%s<object>>|class-string<%s>>', ReflectionClass::class, ReflectionFunctionAbstract::class), Arr::extend($i, ...$j));
assertType('array{foo?: string, bar: int, baz?: string, 0: string|Stringable, 1: float|int|null, 2: bool, 3: true}', Arr::extend($d, ...$f));
assertType('non-empty-array<int|string, bool|float|int|string|Stringable>', Arr::extend($d, ...$g));
assertType('non-empty-array<int|string, bool|float|int|string|Stringable|null>', Arr::extend($e, ...$f));
assertType('non-empty-array<int|string, bool|float|int|string|Stringable>', Arr::extend($e, ...$g));
assertType('non-empty-array', Arr::extend($k));
assertType('non-empty-array', Arr::extend($k, 71));
assertType('array', Arr::extend($l));
assertType('non-empty-array', Arr::extend($l, 71));
assertType('non-empty-array', Arr::extend($l, ...$k));
assertType('array', Arr::extend($l, ...$l));
assertType('non-empty-array', Arr::extend($l, $l));
assertType('array<int, mixed>', Arr::extend($m));
assertType('non-empty-array<int, mixed>', Arr::extend($m, 71));
