<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\ResponseIntegrationTest;
use Salient\Http\HttpResponse;

/**
 * @covers \Salient\Http\HttpResponse
 */
class ResponseTest extends ResponseIntegrationTest
{
    public function createSubject()
    {
        return new HttpResponse();
    }
}
