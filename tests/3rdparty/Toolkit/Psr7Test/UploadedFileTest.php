<?php declare(strict_types=1);

namespace Salient\Tests\Psr7Test;

use Http\Psr7Test\UploadedFileIntegrationTest;
use Salient\Http\Stream;
use Salient\Http\UploadedFile;

/**
 * @covers \Salient\Http\UploadedFile
 */
class UploadedFileTest extends UploadedFileIntegrationTest
{
    public function createSubject()
    {
        $stream = Stream::fromString('foobar');

        return new UploadedFile(
            $stream,
            $stream->getSize(),
            \UPLOAD_ERR_OK,
            'filename.txt',
            'text/plain',
        );
    }
}
