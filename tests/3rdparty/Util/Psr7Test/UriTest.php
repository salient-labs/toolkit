<?php declare(strict_types=1);

namespace Lkrms\Tests\Psr7Test;

use Http\Psr7Test\UriIntegrationTest;
use Salient\Http\Uri;

class UriTest extends UriIntegrationTest
{
    /**
     * @var array<string,string>
     */
    protected $skippedTests = [
        // `http://foo.com///bar` and `http://foo.com/bar` are not necessarily
        // equivalent, and normalising URI paths by removing leading slashes
        // without backend knowledge is not [RFC3986]-compliant
        'testGetPathNormalizesMultipleLeadingSlashesToSingleSlashToPreventXSS' => 'Test is invalid',
        'testPath' => 'Percent-encoded octets are normalised to uppercase per [RFC3986]',
    ];

    public function createUri($uri)
    {
        return (new Uri($uri, false))->normalise();
    }
}
