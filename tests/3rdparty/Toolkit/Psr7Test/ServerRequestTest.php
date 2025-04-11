<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\ServerRequestIntegrationTest;
use Salient\Http\ServerRequest;

/**
 * @covers \Salient\Http\ServerRequest
 * @covers \Salient\Http\Request
 * @covers \Salient\Http\AbstractRequest
 * @covers \Salient\Http\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
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
        return new ServerRequest('GET', '/', $_SERVER);
    }
}
