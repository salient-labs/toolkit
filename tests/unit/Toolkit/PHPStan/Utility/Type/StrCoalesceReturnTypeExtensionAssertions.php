<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Utility\Type;

use Salient\Utility\Str;

use function PHPStan\Testing\assertType;

assertType('null', Str::coalesce());
assertType('null', Str::coalesce(null));
assertType('null', Str::coalesce(null, null));
assertType('null', Str::coalesce('', null));
assertType("''", Str::coalesce(null, ''));
assertType("''", Str::coalesce(''));
assertType("''", Str::coalesce('', ''));
assertType("''", Str::coalesce('', '', ''));
assertType("'foo'", Str::coalesce('foo', ''));
assertType("'foo'", Str::coalesce('', 'foo'));
assertType("'foo'", Str::coalesce('', null, 'foo', ''));
assertType("''", Str::coalesce(false));
assertType('null', Str::coalesce(false, null));
assertType("'1'", Str::coalesce(true));
assertType("'0'", Str::coalesce(0));
assertType("'0'", Str::coalesce(0, null));

$a = [];
$b = [null];
$c = [null, null];
$d = ['', null];
$e = [null, ''];
$f = ['foo', ''];
$g = ['', null, 'foo', ''];
$h = [false];
$i = [false, null];
$j = [true];
$k = [0];
$l = [0, null];

assertType('null', Str::coalesce(...$a));
assertType('null', Str::coalesce(...$a, ...$a));
assertType('null', Str::coalesce(...$b));
assertType('null', Str::coalesce(...$c));
assertType('null', Str::coalesce(...$d));
assertType("''", Str::coalesce(...$e));
assertType("''", Str::coalesce(...$a, ...$d, ...$e));
assertType("'foo'", Str::coalesce(...$f));
assertType("'foo'", Str::coalesce(...$g));
assertType("''", Str::coalesce(...$h));
assertType('null', Str::coalesce(...$i));
assertType("'1'", Str::coalesce(...$j));
assertType("'0'", Str::coalesce(...$k));
assertType("'0'", Str::coalesce(...$l));

foreach ($g as $value) {
    assertType("''|'foo'|null", Str::coalesce('', $value));
}
