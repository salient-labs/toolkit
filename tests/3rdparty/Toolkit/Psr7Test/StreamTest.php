<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\StreamIntegrationTest;
use Psr\Http\Message\StreamInterface;
use Salient\Http\HttpStream;

/**
 * @covers \Salient\Http\HttpStream
 */
class StreamTest extends StreamIntegrationTest
{
    public function createStream($data)
    {
        if ($data instanceof StreamInterface) {
            return $data;
        }
        if (is_string($data)) {
            return HttpStream::fromString($data);
        }
        return new HttpStream($data);
    }
}
