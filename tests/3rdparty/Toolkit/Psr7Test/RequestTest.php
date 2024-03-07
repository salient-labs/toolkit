<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\RequestIntegrationTest;
use Salient\Http\HttpRequest;

class RequestTest extends RequestIntegrationTest
{
    /**
     * @var array<string,string>
     */
    protected $skippedTests = [
        'testGetRequestTargetInOriginFormNormalizesUriWithMultipleLeadingSlashesInPath' => 'Test is invalid',
        'testMethodIsExtendable' => 'Invalid HTTP request types are rejected',
    ];

    public function createSubject()
    {
        return new HttpRequest('GET', '/');
    }
}
