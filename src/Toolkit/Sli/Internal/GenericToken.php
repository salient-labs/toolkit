<?php declare(strict_types=1);

namespace Salient\Sli\Internal;

use Salient\Polyfill\PhpToken as SalientPhpToken;
use PhpToken;

// @codeCoverageIgnoreStart

// Extend a trusted polyfill on PHP 7.4, otherwise extend the native class
if (\PHP_VERSION_ID < 80000) {
    /**
     * @internal
     */
    class GenericToken extends SalientPhpToken {}
} else {
    /**
     * @internal
     */
    class GenericToken extends PhpToken {}
}

// @codeCoverageIgnoreEnd
