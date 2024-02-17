<?php declare(strict_types=1);

namespace Lkrms\Tests\Console;

use Lkrms\Console\Support\ConsoleLoopbackFormat;
use Lkrms\Console\Support\ConsoleManPageFormat;
use Lkrms\Console\Support\ConsoleMarkdownFormat;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Tests\TestCase;
use Salient\Core\Utility\Str;

final class ConsoleFormatterTest extends TestCase
{
    /**
     * @dataProvider formatProvider
     *
     * @param array{int,int}|int|null $wrapToWidth
     */
    public function testFormat(
        string $expected,
        Formatter $formatter,
        string $string,
        bool $unwrap = false,
        $wrapToWidth = null,
        bool $unformat = false,
        string $break = "\n"
    ): void {
        $this->assertSame(
            Str::eolFromNative($expected),
            $formatter->formatTags(
                $string,
                $unwrap,
                $wrapToWidth,
                $unformat,
                $break,
            )
        );
    }

    /**
     * @return array<array{string,Formatter,string,3?:bool,4?:array{int,int}|int|null,5?:bool,6?:string}>
     */
    public static function formatProvider(): array
    {
        $default = new Formatter();
        $loopback = ConsoleLoopbackFormat::getFormatter();
        $markdown = ConsoleMarkdownFormat::getFormatter();
        $manPage = ConsoleManPageFormat::getFormatter();

        $input1 = <<<'EOF'
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

                Indented paragraph.

                `Indented _code span_`

                ```php
                <?php
                /**Indented code block**/
                $a = b($c);
                ```

            __Bold__, _italic_ and <underline>.

            \<This> is \`some escaped` \__text__. \
            It continues on a \*second* line.

            **_Nested \<tags>_ with <\*nested escapes*>.**

            *0*   escapes \
            *1*   with \
            *2*   adjacent \
            *16*  tags
            EOF;

        $input2 = <<<'EOF'
            xxx xx _<xxxx>_ xxx xxxxx xxxx xxx xx
            xxxx xxx ~~__xxxxx__~~ xxxx xxx xx
            xxxx xxx xxxxx xxxx
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

                    Indented paragraph.

                    Indented _code span_

                        <?php
                        /**Indented code block**/
                        $a = b($c);

                Bold, italic and underline.

                <This> is `some escaped` __text__.
                It continues on a *second* line.

                Nested <tags> with *nested escapes*.

                0   escapes
                1   with
                2   adjacent
                16  tags
                EOF,
                $default,
                $input1,
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

                    Indented paragraph.

                    `Indented _code span_`

                    ```php
                    <?php
                    /**Indented code block**/
                    $a = b($c);
                    ```

                __Bold__, _italic_ and <underline>.

                \<This> is \`some escaped` \__text__. \
                It continues on a \*second* line.

                **_Nested \<tags>_ with <\*nested escapes*>.**

                *0*   escapes \
                *1*   with \
                *2*   adjacent \
                *16*  tags
                EOF,
                $loopback,
                $input1,
            ],
            [
                <<<'EOF'
                This is a **`_code span_`** with `inner tags` that are ignored.

                ## HEADING 1

                **Bold**, `italic` and *<u>underline</u>*.

                ## HEADING 2

                <small>**Low-priority information** with *<u>embedded tags.</u>*</small>

                ```php
                <?php
                /**Preformatted code block**/
                $a = b($c);
                ```

                ***HEADING 3***

                    Indented paragraph.

                    **`Indented _code span_`**

                    ```php
                    <?php
                    /**Indented code block**/
                    $a = b($c);
                    ```

                **Bold**, `italic` and *<u>underline</u>*.

                \<This> is \`some escaped` \__text__. \
                It continues on a \*second* line.

                **`Nested <tags>` with *<u>\*nested escapes*</u>*.**

                `0`   escapes \
                `1`   with \
                `2`   adjacent \
                `16`  tags
                EOF,
                $markdown,
                $input1,
            ],
            [
                <<<'EOF'
                This is a **`_code span_`** with inner tags that are ignored.

                # HEADING 1

                **Bold**, *italic* and *underline*.

                # HEADING 2

                **Low-priority information** with *embedded tags.*

                ```php
                <?php
                /**Preformatted code block**/
                $a = b($c);
                ```

                ***HEADING 3***

                    Indented paragraph.

                    **`Indented _code span_`**

                    ```php
                    <?php
                    /**Indented code block**/
                    $a = b($c);
                    ```

                **Bold**, italic and *underline*.

                \<This> is \`some escaped` \__text__. \
                It continues on a \*second* line.

                **Nested \<tags> with *\*nested escapes**.**

                *0*   escapes \
                *1*   with \
                *2*   adjacent \
                *16*  tags
                EOF,
                $manPage,
                $input1,
            ],
            [
                <<<'EOF'
                Heading

                ` <== an unmatched backtick
                EOF,
                $default,
                <<<'EOF'
                ## Heading

                ` <== an unmatched backtick
                EOF,
            ],
            [
                <<<'EOF'
                ## Heading ##

                ` <== an unmatched backtick
                EOF,
                $loopback,
                <<<'EOF'
                ## Heading

                ` <== an unmatched backtick
                EOF,
            ],
            [
                <<<'EOF'
                Heading

                    ` <== an indented backtick
                EOF,
                $default,
                <<<'EOF'
                ## Heading

                    ` <== an indented backtick
                EOF,
            ],
            [
                <<<'EOF'
                ## Heading ##

                    ` <== an indented backtick
                EOF,
                $loopback,
                <<<'EOF'
                ## Heading

                    ` <== an indented backtick
                EOF,
            ],
            [
                <<<'EOF'
                xxx xx xxxx xxx xxxxx
                xxxx xxx xx xxxx xxx
                xxxxx xxxx xxx xx
                xxxx xxx xxxxx xxxx
                EOF,
                $default,
                $input2,
                true,
                21,
            ],
            [
                <<<'EOF'
                xxx xx xxxx xxx xxxxx
                xxxx xxx xx xxxx
                xxx xxxxx xxxx
                xxx xx xxxx xxx
                xxxxx xxxx
                EOF,
                $default,
                $input2,
                true,
                [21, 17],
            ],
            [
                <<<'EOF'
                xxx xx xxxx xxx
                xxxxx xxxx xxx xx xxxx
                xxx xxxxx xxxx xxx xx
                xxxx xxx xxxxx xxxx
                EOF,
                $default,
                $input2,
                true,
                [18, 22],
            ],
            [
                <<<'EOF'
                xxx xx _<xxxx>_ xxx xxxxx
                xxxx xxx xx xxxx
                xxx ~~__xxxxx__~~ xxxx
                xxx xx xxxx xxx
                xxxxx xxxx
                EOF,
                $default,
                $input2,
                true,
                [21, 17],
                true,
            ],
            [
                <<<'EOF'
                xxx xx **<u>xxxx</u>** xxx
                xxxxx xxxx xxx xx xxxx xxx
                <small>**xxxxx**</small>
                xxxx xxx xx xxxx xxx xxxxx
                xxxx
                EOF,
                $markdown,
                $input2,
                true,
                26,
            ],
            [
                <<<'EOF'
                xxx xx **<u>xxxx</u>**
                xxx xxxxx xxxx xxx xx
                xxxx xxx
                <small>**xxxxx**</small>
                xxxx xxx xx xxxx xxx
                xxxxx xxxx
                EOF,
                $markdown,
                $input2,
                true,
                25,
            ],
            [
                <<<'EOF'
                xxx xx **<u>xxxx</u>** xxx
                xxxxx xxxx xxx xx xxxx
                xxx
                <small>**xxxxx**</small>
                xxxx xxx xx xxxx xxx
                xxxxx xxxx
                EOF,
                $markdown,
                $input2,
                true,
                [26, 22],
            ],
            [
                <<<'EOF'
                xxx xx **<u>xxxx</u>** xxx
                xxxxx xxxx xxx xx
                xxxx xxx
                <small>**xxxxx**</small>
                xxxx xxx xx xxxx xxx
                xxxxx xxxx
                EOF,
                $markdown,
                $input2,
                true,
                [26, 21],
            ],
            [
                <<<'EOF'
                xxx xx _<xxxx>_ xxx
                xxxxx xxxx xxx xx xxxx
                xxx
                ~~__xxxxx__~~
                xxxx xxx xx xxxx xxx
                xxxxx xxxx
                EOF,
                $markdown,
                $input2,
                true,
                [26, 22],
                true,
            ],
            [
                <<<EOF
                some\0 text\0
                with\0
                weird\0
                characters\0
                EOF,
                $default,
                <<<EOF
                some\0 text\0 with\0 weird\0 characters\0
                EOF,
                false,
                11,
            ],
            [
                <<<EOF
                some\0 text\0
                  with\0
                  weird\0
                  characters\0
                EOF,
                $default,
                <<<EOF
                some\0 text\0 with\0 weird\0 characters\0
                EOF,
                false,
                11,
                false,
                "\n  ",
            ],
            [
                <<<EOF
                some\0 text\0
                  with\0 weird\0
                  characters\0
                EOF,
                $default,
                <<<EOF
                some\0 text\0 with\0 weird\0 characters\0
                EOF,
                false,
                12,
                false,
                "\n  ",
            ],
            [
                <<<EOF
                some\x7f text\x7f
                  with\x7f
                  weird\x7f
                  characters\x7f
                EOF,
                $default,
                <<<EOF
                some\x7f text\x7f with\x7f weird\x7f characters\x7f
                EOF,
                false,
                11,
                false,
                "\n  ",
            ],
            [
                <<<EOF
                some\x7f text\x7f
                  with\x7f weird\x7f
                  characters\x7f
                EOF,
                $default,
                <<<EOF
                some\x7f text\x7f with\x7f weird\x7f characters\x7f
                EOF,
                false,
                12,
                false,
                "\n  ",
            ],
            [
                <<<EOF
                text without backticks at the end of the line

                text with `backticks` at the end of the line
                EOF,
                $loopback,
                <<<EOF
                text without backticks
                at the end of the line

                text with `backticks`
                at the end of the line
                EOF,
                true,
            ],
        ];
    }

    /**
     * @dataProvider escapeProvider
     */
    public function testEscape(string $expected, string $string, bool $newlines = false): void
    {
        $escaped = Formatter::escapeTags($string, $newlines);
        $this->assertSame($expected, $escaped);
    }

    /**
     * @return array<string,array{string,string,2?:bool}>
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
                '!\#()\*+-.[]\_\`{}\\\\',
                '!#()*+-.[]_`{}\\',
            ],
            'CommonMark syntax' => [
                '!"\#$%&\'()\*+,-./:;\<=\>?@[]^\_\`{|}\~\\\\',
                '!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~\\',
            ],
        ];
    }
}
