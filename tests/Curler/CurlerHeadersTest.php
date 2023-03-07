<?php declare(strict_types=1);

namespace Lkrms\Tests\Curler;

use Lkrms\Curler\CurlerHeaders;

final class CurlerHeadersTest extends \Lkrms\Tests\TestCase
{
    public function testImmutability()
    {
        $headers = new CurlerHeaders();
        $change1 = $headers->setHeader('A', '1');
        $change2 = $change1->setHeader('B', '2');

        $this->assertNotSame($headers, $change1);
        $this->assertNotSame($change1, $change2);

        $this->assertNotEquals($headers->getHeaderValues(), $change1->getHeaderValues());
        $this->assertNotEquals($change1->getHeaderValues(), $change2->getHeaderValues());
    }
}
