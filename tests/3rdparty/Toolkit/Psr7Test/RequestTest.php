<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\RequestIntegrationTest;
use Salient\Http\Request;

/**
 * @covers \Salient\Http\Request
 * @covers \Salient\Http\AbstractRequest
 * @covers \Salient\Http\AbstractMessage
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
