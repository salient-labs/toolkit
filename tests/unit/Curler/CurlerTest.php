<?php declare(strict_types=1);

namespace Lkrms\Tests\Curler;

use Lkrms\Curler\Curler;

final class CurlerTest extends \Lkrms\Tests\TestCase
{
    public function testCurler()
    {
        $this->expectNotToPerformAssertions();
        return;

        // $curler = new Curler('https://api.github.com/meta');
        // $data = $curler->get();
        // $this->assertArrayHasKey('web', $data);
    }
}
