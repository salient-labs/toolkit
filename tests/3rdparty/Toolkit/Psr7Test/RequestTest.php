<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\RequestIntegrationTest;
use Salient\Http\Message\Request;

/**
 * @covers \Salient\Http\Message\Request
 * @covers \Salient\Http\Message\AbstractRequest
 * @covers \Salient\Http\Message\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
 */
class RequestTest extends RequestIntegrationTest
{
    /**
     * @var array<string,string>
     */
    protected $skippedTests = [
        'testGetRequestTargetInOriginFormNormalizesUriWithMultipleLeadingSlashesInPath' => 'Test is invalid',
    ];

    public function createSubject()
    {
        return new Request('GET', '/');
    }
}
