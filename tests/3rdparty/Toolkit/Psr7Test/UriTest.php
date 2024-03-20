<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\UriIntegrationTest;
use Psr\Http\Message\UriInterface;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Http\Uri;

class UriTest extends UriIntegrationTest
{
    /**
     * @var array<string,string>
     */
    protected $skippedTests = [
        // `http://foo.com///bar` and `http://foo.com/bar` are not necessarily
        // equivalent, and normalising URI paths by removing leading slashes is
        // not [RFC3986]-compliant
        'testGetPathNormalizesMultipleLeadingSlashesToSingleSlashToPreventXSS' => 'Test is invalid',
    ];

    public function createUri($uri)
    {
        return new Uri($uri);
    }

    /**
     * @return array<array{UriInterface,string}>
     */
    public function getPaths(): array
    {
        /** @var array<array{UriInterface,string}> */
        $data = parent::getPaths();
        foreach ($data as &$args) {
            // Convert percent-encoded octets to uppercase per [RFC3986]
            $args[1] = Pcre::replaceCallbackArray([
                '/%([0-9a-f]{2})/i' =>
                    fn(array $matches) => '%' . Str::upper($matches[1]),
            ], $args[1]);
        }
        return $data;
    }
}
