<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type\Core;

use Salient\Core\Utility\Get;

use function PHPStan\Testing\assertType;

assertType('null', Get::coalesce(null));
assertType('0', Get::coalesce(null, 0));
assertType('0', Get::coalesce(0, null));
assertType('0', Get::coalesce(null, 0, null));
assertType('false', Get::coalesce(null, false));
assertType('false', Get::coalesce(false, null));
assertType('false', Get::coalesce(null, false, null));
assertType("''", Get::coalesce(null, ''));
assertType("''", Get::coalesce('', null));
assertType("''", Get::coalesce(null, '', null));
assertType("''", Get::coalesce('', 'foo'));
assertType("'foo'", Get::coalesce('foo', ''));
assertType("'foo'", Get::coalesce(null, 'foo'));
assertType("'foo'", Get::coalesce('foo', null));
assertType("'foo'", Get::coalesce(null, 'foo', null));
assertType('array{}', Get::coalesce([], null));
assertType('array{null}', Get::coalesce([null], null));
assertType("array{'foo'}", Get::coalesce(['foo'], null));

$a = [];
$b = [null];
$c = [null, 0];
$d = [0, null];
$e = [null, 0, null];
$f = [null, false];
$g = [false, null];
$h = [null, false, null];
$i = [null, ''];
$j = ['', null];
$k = [null, '', null];
$l = ['', 'foo'];
$m = ['foo', ''];
$n = [null, 'foo'];
$o = ['foo', null];
$p = [null, 'foo', null];
$q = [[], null];
$r = [[null], null];
$s = [['foo'], null];
$t = [null, false, true, 0, 1, 3.14, 'foo', '', ['foo'], ''];

assertType('null', Get::coalesce(...$a));
assertType('null', Get::coalesce(...$a, ...$a));
assertType('null', Get::coalesce(...$b));
assertType('0', Get::coalesce(...$c));
assertType('0', Get::coalesce(...$d));
assertType('0', Get::coalesce(...$e));
assertType('false', Get::coalesce(...$f));
assertType('false', Get::coalesce(...$g));
assertType('false', Get::coalesce(...$h));
assertType("''", Get::coalesce(...$i));
assertType("''", Get::coalesce(...$j));
assertType("''", Get::coalesce(...$k));
assertType("''", Get::coalesce(...$l));
assertType("''", Get::coalesce(...$a, ...$b, ...$l));
assertType("'foo'", Get::coalesce(...$m));
assertType("'foo'", Get::coalesce(...$n));
assertType("'foo'", Get::coalesce(...$o));
assertType("'foo'", Get::coalesce(...$p));
assertType('array{}', Get::coalesce(...$q));
assertType('array{null}', Get::coalesce(...$r));
assertType("array{'foo'}", Get::coalesce(...$s));

foreach ($t as $u) {
    assertType("0|1|3.14|''|'foo'|array{'foo'}|bool", Get::coalesce($u, ''));
    assertType("0|1|3.14|''|'bar'|'foo'|array{'foo'}|bool", Get::coalesce($u, 'bar'));
    assertType("0|1|3.14|''|'foo'|array{'foo'}|bool|null", Get::coalesce($u, null));
}
