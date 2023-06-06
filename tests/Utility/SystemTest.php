<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\System;
use RuntimeException;

final class SystemTest extends \Lkrms\Tests\TestCase
{
    public function testStartTimer()
    {
        $system = new System();

        $time0 = hrtime(true) / 1000000;
        $system->startTimer('primary');
        $system->startTimer('secondary');
        usleep(25000);
        $time1 = hrtime(true) / 1000000;

        $system->startTimer('other', 'special');
        $secondary = $system->stopTimer('secondary');
        usleep(50000);
        $time2 = hrtime(true) / 1000000;

        $system->startTimer('secondary');
        usleep(10000);
        $time3 = hrtime(true) / 1000000;

        $system->stopTimer('secondary');
        $system->stopTimer('other', 'special');
        usleep(25000);
        $time4 = hrtime(true) / 1000000;

        $timers = $system->getTimers();
        $generalTimers = $system->getTimers(true, 'general');
        $specialTimers = $system->getTimers(true, 'special');
        $stoppedTimers = $system->getTimers(false);
        $this->assertEqualsWithDelta($time1 - $time0, $secondary, 1);
        $this->assertEqualsWithDelta($time4 - $time0, $timers['general']['primary'][0], 1);
        $this->assertEqualsWithDelta(($time1 - $time0) + ($time3 - $time2), $timers['general']['secondary'][0], 1);
        $this->assertEqualsWithDelta($time3 - $time1, $timers['special']['other'][0], 1);
        $this->assertSame(1, $timers['general']['primary'][1]);
        $this->assertSame(2, $timers['general']['secondary'][1]);
        $this->assertSame(1, $timers['special']['other'][1]);
        $this->assertArrayHasSignature(['general'], $generalTimers);
        $this->assertArrayHasSignature(['primary', 'secondary'], $generalTimers['general']);
        $this->assertArrayHasSignature(['special'], $specialTimers);
        $this->assertArrayHasSignature(['other'], $specialTimers['special']);
        $this->assertArrayHasSignature(['secondary'], $stoppedTimers['general']);
        $this->assertArrayHasSignature(['other'], $stoppedTimers['special']);
        $this->expectException(RuntimeException::class);
        $system->startTimer('primary');
    }

    public function testStopTimer()
    {
        $this->expectException(RuntimeException::class);
        $system = new System();
        $system->stopTimer('primary');
    }

    public function testGetWorkingDirectory()
    {
        $system = new System();
        $cwd = $system->getCwd();
        $this->assertSame(fileinode($cwd), fileinode(getcwd()));
    }
}
