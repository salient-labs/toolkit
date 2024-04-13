<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\UploadedFileIntegrationTest;
use Salient\Http\HttpServerRequestUpload;
use Salient\Http\HttpStream;

/**
 * @covers \Salient\Http\HttpServerRequestUpload
 */
class UploadedFileTest extends UploadedFileIntegrationTest
{
    public function createSubject()
    {
        $stream = HttpStream::fromString('foobar');

        return new HttpServerRequestUpload(
            $stream,
            $stream->getSize(),
            \UPLOAD_ERR_OK,
            'filename.txt',
            'text/plain',
        );
    }
}
