<?php declare(strict_types=1);

namespace Lkrms\Tests\Console;

use Lkrms\Console\ConsoleFormatter;

final class ConsoleFormatterTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider escapeProvider
     */
    public function testEscape(string $expected, string $string, bool $newlines = false): void
    {
        $escaped = ConsoleFormatter::escape($string, $newlines);
        $this->assertSame($expected, $escaped);
    }

    /**
     * @return array<string,array{0:string,1:string,2?:bool}>
     */
    public static function escapeProvider(): array
    {
        return [
            'empty string' => [
                '',
                '',
            ],
            'backslash' => [
                '\\\\',
                '\\',
            ],
            'backtick' => [
                '\`',
                '`',
            ],
            'backslash + backtick #1' => [
                '\\\\\\`',
                '\`',
            ],
            'backslash + backtick #2' => [
                '\`\\\\',
                '`\\',
            ],
            'backslash + backtick #3' => [
                'Some \\\\\\`escaped\\\\\\` \\\\\\`text\\\\\\`',
                'Some \`escaped\` \`text\`',
            ],
            'newline' => [
                "abc\\\ndef\\\n",
                "abc\ndef\n",
                true,
            ],
            'Markdown syntax' => [
                '\!\#\(\)\*\+\-\.\[\]\_\`\{\}\\\\',
                '!#()*+-.[]_`{}\\',
            ],
            'CommonMark syntax' => [
                '\!\"\#\$\%\&\\\'\(\)\*\+\,\-\.\/\:\;\<\=\>\?\@\[\]\^\_\`\{\|\}\~\\\\',
                '!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~\\',
            ],
        ];
    }
}
