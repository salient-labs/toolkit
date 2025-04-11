<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\UploadedFileIntegrationTest;
use Salient\Http\ServerRequestUpload;
use Salient\Http\Stream;

/**
 * @covers \Salient\Http\ServerRequestUpload
 */
class UploadedFileTest extends UploadedFileIntegrationTest
{
    public function createSubject()
    {
        $stream = Stream::fromString('foobar');

        return new ServerRequestUpload(
            $stream,
            $stream->getSize(),
            \UPLOAD_ERR_OK,
            'filename.txt',
            'text/plain',
        );
    }
}
