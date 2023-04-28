<?php declare(strict_types=1);

namespace Lkrms\Tests\Curler;

use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\CurlerHeadersFlag;
use Lkrms\Support\Catalog\HttpHeader;

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

    public function testPattern()
    {
        $headers = (new CurlerHeaders())
            ->addHeader(HttpHeader::PREFER, 'respond-async')
            ->addHeader(HttpHeader::PREFER, 'wait=5')
            ->addHeader(HttpHeader::PREFER, 'handling=lenient')
            ->addHeader(HttpHeader::PREFER, 'task_priority=2')
            ->addHeader(HttpHeader::PREFER, 'ODATA.maxpagesize=100');
        $this->assertSame(true, $headers->hasHeader(HttpHeader::PREFER, '/^wait\h*=/i'));
        $this->assertSame(true, $headers->hasHeader(HttpHeader::PREFER, '/^odata\./i'));
        $this->assertSame(false, $headers->hasHeader(HttpHeader::PREFER, '/^return\h*=/i'));

        $headers = $headers->unsetHeader(HttpHeader::PREFER, '/^odata\./i');
        $this->assertSame(false, $headers->hasHeader(HttpHeader::PREFER, '/^odata\./i'));
        $headers = $headers->addHeader(HttpHeader::PREFER, 'odata.maxpagesize=50');
        $this->assertSame(
            [
                [
                    'prefer' => [
                        0 => 'respond-async',
                        1 => 'wait=5',
                        2 => 'handling=lenient',
                        3 => 'task_priority=2',
                        5 => 'odata.maxpagesize=50',
                    ],
                ],
                [
                    'prefer' => 'respond-async, wait=5, handling=lenient, task_priority=2, odata.maxpagesize=50',
                ],
                'odata.maxpagesize=50, odata.track-changes',
            ],
            [
                $headers->getHeaderValues(),
                $headers->getHeaderValues(CurlerHeadersFlag::COMBINE),
                $headers->addHeader(HttpHeader::PREFER, 'odata.track-changes')
                        ->getHeaderValue(HttpHeader::PREFER, CurlerHeadersFlag::COMBINE, '/^odata\./i'),
            ]
        );
    }
}
