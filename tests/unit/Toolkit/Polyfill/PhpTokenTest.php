<?php declare(strict_types=1);

namespace Salient\Tests\Polyfill;

use Salient\Polyfill\PhpToken;

/**
 * @covers \Salient\Polyfill\PhpToken
 */
final class PhpTokenTest extends PhpTokenTestCase
{
    protected static function getToken(): string
    {
        return PhpToken::class;
    }
}
