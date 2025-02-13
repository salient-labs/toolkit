<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\MetricCollector;
use Salient\Tests\TestCase;
use LogicException;

/**
 * @covers \Salient\Core\MetricCollector
 */
final class MetricCollectorTest extends TestCase
{
    public function testCounters(): void
    {
        $collector = new MetricCollector();

        for ($i = 0; $i < 13; $i++) {
            if ($i % 2) {
                $collector->count('odd', 'type');
            } else {
                $collector->count('even', 'type');
            }
            if ($i % 3 === 0) {
                $collector->count('divisible-by-three');
            }
            $collector->add($i, 'sum');
        }

        $all = [
            'type' => [
                'even' => 7,
                'odd' => 6,
            ],
            'general' => [
                'divisible-by-three' => 5,
                'sum' => 78,
            ]
        ];
        $this->assertSame($all, $collector->getCounters());
        $this->assertSame([], $collector->getCounters('*'));
        $this->assertSame($all, $collector->getCounters(['*']));
        $this->assertSame($all['type'], $collector->getCounters('type'));
        $this->assertSame(['type' => $all['type']], $collector->getCounters(['type']));
        $this->assertSame(7, $collector->getCounter('even', 'type'));
        $this->assertSame(0, $collector->getCounter('even'));
    }

    public function testStartTimer(): void
    {
        $collector = new MetricCollector();

        $time0 = hrtime(true) / 1000000;
        $collector->startTimer('primary');
        $collector->startTimer('secondary');
        usleep(100000);
        $time1 = hrtime(true) / 1000000;

        $collector->startTimer('other', 'special');
        $secondary = $collector->stopTimer('secondary');
        usleep(200000);
        $time2 = hrtime(true) / 1000000;

        $collector->startTimer('secondary');
        usleep(75000);
        $time3 = hrtime(true) / 1000000;

        $collector->stopTimer('secondary');
        $collector->stopTimer('other', 'special');
        usleep(100000);
        $time4 = hrtime(true) / 1000000;

        $timers = $collector->getTimers();
        $generalTimers = $collector->getTimers(true, ['general']);
        $specialTimers = $collector->getTimers(true, 'special');
        $stoppedTimers = $collector->getTimers(false);

        $expected = [
            'primary' => $time4 - $time0,
            'secondary' => ($time1 - $time0) + ($time3 - $time2),
            'other' => $time3 - $time1,
        ];

        $expectedRuns = [
            'primary' => 1,
            'secondary' => 2,
            'other' => 1,
        ];

        // Allow timing to be off by up to 16ms because of [Windows] timer
        // granularity
        $this->assertEqualsWithDelta($time1 - $time0, $secondary, 16);
        $this->assertEqualsWithDelta($expected['primary'], $timers['general']['primary'][0], 16);
        $this->assertEqualsWithDelta($expected['primary'], $collector->getTimer('primary')[0], 16);
        $this->assertEqualsWithDelta($expected['secondary'], $timers['general']['secondary'][0], 16);
        $this->assertEqualsWithDelta($expected['secondary'], $collector->getTimer('secondary')[0], 16);
        $this->assertEqualsWithDelta($expected['other'], $timers['special']['other'][0], 16);
        $this->assertEqualsWithDelta($expected['other'], $collector->getTimer('other', 'special')[0], 16);
        $this->assertSame($expectedRuns['primary'], $timers['general']['primary'][1]);
        $this->assertSame($expectedRuns['primary'], $collector->getTimer('primary')[1]);
        $this->assertSame($expectedRuns['secondary'], $timers['general']['secondary'][1]);
        $this->assertSame($expectedRuns['secondary'], $collector->getTimer('secondary')[1]);
        $this->assertSame($expectedRuns['other'], $timers['special']['other'][1]);
        $this->assertSame($expectedRuns['other'], $collector->getTimer('other', 'special')[1]);
        $this->assertSame(['general'], array_keys($generalTimers));
        $this->assertSame(['primary', 'secondary'], array_keys($generalTimers['general']));
        $this->assertSame(['other'], array_keys($specialTimers));
        $this->assertSame(['secondary'], array_keys($stoppedTimers['general']));
        $this->assertSame(['other'], array_keys($stoppedTimers['special']));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Timer already running: primary');
        $collector->startTimer('primary');
    }

    public function testStopTimer(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Timer not running: primary');
        (new MetricCollector())->stopTimer('primary');
    }

    public function testStartTimerWithCounterName(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not a timer: counter (group=general)');
        $collector = new MetricCollector();
        $collector->count('counter');
        $collector->startTimer('counter');
    }

    public function testCountWithTimerName(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not a counter: timer (group=general)');
        $collector = new MetricCollector();
        $collector->startTimer('timer');
        $collector->count('timer');
    }

    public function testPush(): void
    {
        $collector = new MetricCollector();

        $time0 = hrtime(true) / 1000000;
        $collector->count('counter1');
        $collector->count('counter2', 'special');
        $collector->startTimer('timer1');
        $collector->startTimer('timer2', 'special');
        usleep(75000);
        $time1 = hrtime(true) / 1000000;
        $collector->stopTimer('timer1');

        // --
        $collector->push();
        $collector->count('counter2', 'special');
        $collector->count('counter3', 'special');
        $collector->startTimer('timer1');
        $collector->startTimer('timer3', 'special');
        usleep(75000);
        $time2 = hrtime(true) / 1000000;
        $collector->stopTimer('timer1');
        $beforePop = [$collector->getCounters(), $collector->getTimers()];
        $maxTimersBeforePop = $collector->getMaxTimers();
        // --

        $collector->pop();
        $afterPop = [$collector->getCounters(), $collector->getTimers()];
        $maxTimersAfterPop = $collector->getMaxTimers();

        $this->assertSame([
            'general' => ['counter1' => 1],
            'special' => ['counter2' => 2, 'counter3' => 1],
        ], $beforePop[0]);
        $this->assertSame(['timer1'], array_keys($beforePop[1]['general']));
        $this->assertSame(['timer2', 'timer3'], array_keys($beforePop[1]['special']));
        $this->assertEqualsWithDelta($time2 - $time0, $beforePop[1]['general']['timer1'][0], 16 * 2);
        $this->assertEqualsWithDelta($time2 - $time0, $beforePop[1]['special']['timer2'][0], 16);
        $this->assertEqualsWithDelta($time2 - $time1, $beforePop[1]['special']['timer3'][0], 16);
        $this->assertSame(2, $beforePop[1]['general']['timer1'][1]);
        $this->assertSame(1, $beforePop[1]['special']['timer2'][1]);
        $this->assertSame(1, $beforePop[1]['special']['timer3'][1]);
        $this->assertSame([
            'general' => 1,
            'special' => 2,
        ], $maxTimersBeforePop);

        $this->assertSame([
            'general' => ['counter1' => 1],
            'special' => ['counter2' => 1],
        ], $afterPop[0]);
        $this->assertSame(['timer1'], array_keys($afterPop[1]['general']));
        $this->assertSame(['timer2'], array_keys($afterPop[1]['special']));
        $this->assertEqualsWithDelta($time1 - $time0, $afterPop[1]['general']['timer1'][0], 16);
        $this->assertEqualsWithDelta($time2 - $time0, $afterPop[1]['special']['timer2'][0], 16);
        $this->assertSame(1, $afterPop[1]['general']['timer1'][1]);
        $this->assertSame(1, $afterPop[1]['special']['timer2'][1]);
        $this->assertSame([
            'general' => 1,
            'special' => 1,
        ], $maxTimersAfterPop);
    }
}
