<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\TestCase;
use Lkrms\Utility\Sys;

final class SysTest extends TestCase
{
    /**
     * @dataProvider escapeCommandProvider
     */
    public function testEscapeCommand(string $arg): void
    {
        $command = [
            \PHP_BINARY,
            '-ddisplay_startup_errors=0',
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
}
