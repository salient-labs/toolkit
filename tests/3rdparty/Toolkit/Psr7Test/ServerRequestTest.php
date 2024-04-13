<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\ServerRequestIntegrationTest;
use Salient\Http\HttpServerRequest;

/**
 * @covers \Salient\Http\HttpServerRequest
 * @covers \Salient\Http\HttpRequest
 * @covers \Salient\Http\AbstractHttpMessage
 */
class ServerRequestTest extends ServerRequestIntegrationTest
{
    /**
     * @var array<string,string>
     */
    protected $skippedTests = [
        'testGetRequestTargetInOriginFormNormalizesUriWithMultipleLeadingSlashesInPath' => 'Test is invalid',
    ];

    public function createSubject()
    {
        return new HttpServerRequest('GET', '/', $_SERVER);
    }
}
