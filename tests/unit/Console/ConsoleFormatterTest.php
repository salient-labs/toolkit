<?php declare(strict_types=1);

namespace Lkrms\Tests\Console;

use Lkrms\Console\ConsoleFormatter;
use Lkrms\Console\ConsoleTagFormats;

final class ConsoleFormatterTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider formatProvider
     */
    public function testFormat(
        string $expected,
        ConsoleFormatter $formatter,
        string $string,
        bool $unwrap = false,
        ?int $width = null,
        bool $unescape = true
    ): void {
        $this->assertSame($expected, $formatter->format($string, $unwrap, $width, $unescape));
    }

    /**
     * @return array<array{0:string,1:ConsoleFormatter,2:string,3?:bool,4?:int|null,5?:bool}>
     */
    public static function formatProvider(): array
    {
        $default = new ConsoleFormatter();
        $loopback = new ConsoleFormatter(ConsoleTagFormats::getLoopbackFormats());

        $input = <<<'EOF'
            This is a `_code span_` with _inner tags_ that are ignored.

            ## HEADING 1

            **Bold**, *italic* and <underline>.

            ## HEADING 2 ##

            ~~**Low-priority information** with <embedded tags.>~~

            ```php
            <?php
            /**Preformatted code block**/
            $a = b($c);
            ```

            ___HEADING 3___

            __Bold__, _italic_ and <underline>.

            \<This> is \`some escaped` \__text__. \
            It continues on a \*second* line.

            **_Nested \<tags>_ with <\*nested escapes*>.**
            EOF;

        return [
            [
                <<<'EOF'
                This is a _code span_ with inner tags that are ignored.

                HEADING 1

                Bold, italic and underline.

                HEADING 2

                Low-priority information with embedded tags.

                <?php
                /**Preformatted code block**/
                $a = b($c);

                HEADING 3

                Bold, italic and underline.

                <This> is `some escaped` __text__.
                It continues on a *second* line.

                Nested <tags> with *nested escapes*.
                EOF,
                $default,
                $input,
            ],
            [
                <<<'EOF'
                This is a `_code span_` with _inner tags_ that are ignored.

                ## HEADING 1 ##

                **Bold**, *italic* and <underline>.

                ## HEADING 2 ##

                ~~**Low-priority information** with <embedded tags.>~~

                ```php
                <?php
                /**Preformatted code block**/
                $a = b($c);
                ```

                ___HEADING 3___

                __Bold__, _italic_ and <underline>.

                \<This> is \`some escaped` \__text__. \
                It continues on a \*second* line.

                **_Nested \<tags>_ with <\*nested escapes*>.**
                EOF,
                $loopback,
                $input,
                false,
                null,
                false,
            ],
        ];
    }

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
