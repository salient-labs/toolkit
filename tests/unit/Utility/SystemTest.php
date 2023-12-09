<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Support\Timekeeper;
use Lkrms\Utility\Sys;
use LogicException;

final class SystemTest extends \Lkrms\Tests\TestCase
{
    public function testStartTimer(): void
    {
        $system = new Timekeeper();

        $time0 = hrtime(true) / 1000000;
        $system->startTimer('primary');
        $system->startTimer('secondary');
        usleep(100000);
        $time1 = hrtime(true) / 1000000;

        $system->startTimer('other', 'special');
        $secondary = $system->stopTimer('secondary');
        usleep(200000);
        $time2 = hrtime(true) / 1000000;

        $system->startTimer('secondary');
        usleep(75000);
        $time3 = hrtime(true) / 1000000;

        $system->stopTimer('secondary');
        $system->stopTimer('other', 'special');
        usleep(100000);
        $time4 = hrtime(true) / 1000000;

        $timers = $system->getTimers();
        $generalTimers = $system->getTimers(true, 'general');
        $specialTimers = $system->getTimers(true, 'special');
        $stoppedTimers = $system->getTimers(false);
        // Allow timing to be off by up to 16ms because of [Windows] timer
        // granularity
        $this->assertEqualsWithDelta($time1 - $time0, $secondary, 16);
        $this->assertEqualsWithDelta($time4 - $time0, $timers['general']['primary'][0], 16);
        $this->assertEqualsWithDelta(($time1 - $time0) + ($time3 - $time2), $timers['general']['secondary'][0], 16);
        $this->assertEqualsWithDelta($time3 - $time1, $timers['special']['other'][0], 16);
        $this->assertSame(1, $timers['general']['primary'][1]);
        $this->assertSame(2, $timers['general']['secondary'][1]);
        $this->assertSame(1, $timers['special']['other'][1]);
        $this->assertSame(['general'], array_keys($generalTimers));
        $this->assertSame(['primary', 'secondary'], array_keys($generalTimers['general']));
        $this->assertSame(['special'], array_keys($specialTimers));
        $this->assertSame(['other'], array_keys($specialTimers['special']));
        $this->assertSame(['secondary'], array_keys($stoppedTimers['general']));
        $this->assertSame(['other'], array_keys($stoppedTimers['special']));
        $this->expectException(LogicException::class);
        $system->startTimer('primary');
    }

    public function testStopTimer(): void
    {
        $this->expectException(LogicException::class);
        $system = new Timekeeper();
        $system->stopTimer('primary');
    }

    /**
     * @dataProvider escapeCommandProvider
     */
    public function testEscapeCommand(string $arg): void
    {
        $command = [
            \PHP_BINARY,
            $this->getFixturesPath(__CLASS__) . '/unescape.php',
            $arg,
        ];
        $command = Sys::escapeCommand($command);
        $handle = popen($command, 'rb');
        $output = stream_get_contents($handle);
        $status = pclose($handle);
        $this->assertSame(0, $status);
        $this->assertSame($arg . \PHP_EOL, $output);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function escapeCommandProvider(): array
    {
        return [
            'empty string' => [
                '',
            ],
            'special characters' => [
                '!"$%&\'*+,;<=>?[\]^`{|}~',
            ],
            'special characters + whitespace' => [
                ' ! " $ % & \' * + , ; < = > ? [ \ ] ^ ` { | } ~ ',
            ],
            'quoted' => [
                '"string"',
            ],
            'quoted + backslashes' => [
                '"\string\"',
            ],
            'quoted + whitespace' => [
                '"string with words"',
            ],
            'quoted + whitespace + backslashes' => [
                '"\string with words\"',
            ],
            'unquoted + special (cmd) #1' => [
                'this&that',
            ],
            'unquoted + special (cmd) #2' => [
                'this^that',
            ],
            'unquoted + special (cmd) #3' => [
                '(this|that)',
            ],
            'cmd variable expansion #1' => [
                '%path%',
            ],
            'cmd variable expansion #2' => [
                '!path!',
            ],
            'cmd variable expansion #3' => [
                'value%',
            ],
            'cmd variable expansion #4' => [
                'success!',
            ],
        ];
    }

    public function testGetCwd(): void
    {
        $cwd = Sys::getCwd();
        $this->assertSame(fileinode($cwd), fileinode(getcwd()));
    }
}
