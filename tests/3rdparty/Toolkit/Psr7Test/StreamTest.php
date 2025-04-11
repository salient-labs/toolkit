<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\StreamIntegrationTest;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Http\Message\Stream;

/**
 * @covers \Salient\Http\Message\Stream
 */
class StreamTest extends StreamIntegrationTest
{
    public function createStream($data)
    {
        if ($data instanceof PsrStreamInterface) {
            return $data;
        }
        if (is_string($data)) {
            return Stream::fromString($data);
        }
        return new Stream($data);
    }
}
