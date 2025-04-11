<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\ResponseIntegrationTest;
use Salient\Http\Message\Response;

/**
 * @covers \Salient\Http\Message\Response
 * @covers \Salient\Http\Message\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
 */
class ResponseTest extends ResponseIntegrationTest
{
    public function createSubject()
    {
        return new Response();
    }
}
