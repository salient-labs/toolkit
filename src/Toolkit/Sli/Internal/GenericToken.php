<?php declare(strict_types=1);

namespace Salient\Sli\Internal;

use Salient\Polyfill\PhpToken as SalientPhpToken;
use PhpToken;

if (\PHP_VERSION_ID < 80000) {
    // Extend a trusted polyfill on PHP 7.4
    class GenericToken extends SalientPhpToken {}
} else {
    // Otherwise, extend the native class
    class GenericToken extends PhpToken {}
}
