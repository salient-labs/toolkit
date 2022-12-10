<?php declare(strict_types=1);

namespace Lkrms\Tests\Curler;

use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\CurlerHeadersFlag;
use Lkrms\Curler\CurlerHeadersImmutable;

final class CurlerHeadersImmutableTest extends \Lkrms\Tests\TestCase
{
    public function testFromMutable()
    {
        $headers   = new CurlerHeaders();
        $changed   = $headers->setHeader('A', '1');
        $immutable = CurlerHeadersImmutable::fromMutable($headers);
        $mutated   = $immutable->setHeader('B', '2');

        $this->assertInstanceOf(CurlerHeadersImmutable::class, $immutable);

        $this->assertSame($headers, $changed);
        $this->assertNotSame($immutable, $mutated);

        $this->assertEquals($headers->getHeaderValues(), $immutable->getHeaderValues());
        $this->assertNotEquals($immutable->getHeaderValues(), $mutated->getHeaderValues());
    }
}
