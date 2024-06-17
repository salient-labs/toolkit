<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type\Core;

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
/** @var non-empty-array<int|float> */
$l = [];
/** @var list<int|float|string|bool|Stringable|null> */
$h = [];
/** @var list<int|float|non-empty-string|true> */
$i = [];
/** @var array{foo:Stringable,bar:string,baz:int,qux?:float,quux:non-empty-string} */
$j = [];

assertType('array<bool|float|int|string|Stringable>', Arr::whereNotEmpty());  // @phpstan-ignore arguments.count, argument.templateType, argument.templateType
assertType('array<bool|float|int|string|Stringable>', Arr::whereNotEmpty($a));  // @phpstan-ignore argument.type, argument.templateType, argument.templateType
assertType('array{}', Arr::whereNotEmpty($b));
assertType('array{}', Arr::whereNotEmpty($c));
assertType("array{3: 0, 4: 0.0, 5: '0'}", Arr::whereNotEmpty($d));
assertType('array{}', Arr::whereNotEmpty($e));
assertType("array{quuux: '0', quux: 0.0, qux: 0}", Arr::whereNotEmpty($f));
assertType('array<float|int|non-empty-string|true>', Arr::whereNotEmpty($g));
assertType('array<float|int|non-empty-string|true>', Arr::whereNotEmpty($k));
assertType('non-empty-array<float|int>', Arr::whereNotEmpty($l));
assertType('array<int, float|int|non-empty-string|Stringable|true>', Arr::whereNotEmpty($h));
assertType('array<int, float|int|non-empty-string|true>', Arr::whereNotEmpty($i));
assertType('array{foo?: Stringable, bar?: non-empty-string, baz: int, qux?: float, quux: non-empty-string}', Arr::whereNotEmpty($j));
