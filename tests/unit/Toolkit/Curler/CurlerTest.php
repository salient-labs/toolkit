<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use Salient\Curler\Exception\CurlerCurlErrorException;
use Salient\Curler\Exception\CurlerHttpErrorException;
use Salient\Curler\Curler;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Curler\Curler
 */
final class CurlerTest extends TestCase
{
    public function testCurlError(): void
    {
        $this->expectException(CurlerCurlErrorException::class);

        (new Curler('http://<localhost>/path'))->get();
    }

    public function testHttpError(): void
    {
        $this->expectException(CurlerHttpErrorException::class);

        (new Curler('http://localhost:3001'))->get();
    }
}
