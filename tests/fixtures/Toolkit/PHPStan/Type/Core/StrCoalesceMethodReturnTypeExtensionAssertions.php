<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type\Core;

use Salient\Core\Utility\Str;

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

$a = [];
$b = [null];
$c = [null, null];
$d = ['', null];
$e = [null, ''];
$f = ['foo', ''];
$g = ['', null, 'foo', ''];

assertType('null', Str::coalesce(...$a));
assertType('null', Str::coalesce(...$a, ...$a));
assertType('null', Str::coalesce(...$b));
assertType('null', Str::coalesce(...$c));
assertType('null', Str::coalesce(...$d));
assertType("''", Str::coalesce(...$e));
assertType("''", Str::coalesce(...$a, ...$d, ...$e));
assertType("'foo'", Str::coalesce(...$f));
assertType("'foo'", Str::coalesce(...$g));

foreach ($g as $h) {
    assertType("''|'foo'|null", Str::coalesce('', $h));
}
