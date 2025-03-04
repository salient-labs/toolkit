<?php declare(strict_types=1);

namespace Salient\Tests\Polyfill;

use PhpToken;

/**
 * @requires PHP >= 8.0
 *
 * @coversNothing
 */
final class NativePhpTokenTest extends PhpTokenTestCase
{
    protected static function getToken(): string
    {
        return PhpToken::class;
    }
}
