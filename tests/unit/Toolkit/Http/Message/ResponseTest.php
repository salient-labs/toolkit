<?php declare(strict_types=1);

namespace Salient\Tests\Http\Message;

use Salient\Http\Message\Response;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Http\Message\Response
 * @covers \Salient\Http\Message\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
 */
final class ResponseTest extends TestCase
{
    public function testToStringWithNoHeaders(): void
    {
        $r = new Response();
        $this->assertSame(implode("\r\n", [
            'HTTP/1.1 200 OK',
            '',
            '',
        ]), (string) $r);
        $this->assertSame(implode("\r\n", [
            'HTTP/1.1 200 OK',
            '',
            'content',
        ]), (string) $r->withBody('content'));
    }
}
