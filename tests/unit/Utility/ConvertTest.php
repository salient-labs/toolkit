<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Http\Uri;
use Lkrms\Support\Date\DateFormatter;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Str;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionParameter;

final class ConvertTest extends TestCase
{
    /**
     * @dataProvider toBoolProvider
     *
     * @param mixed $value
     */
    public function testToBool(?bool $expected, $value): void
    {
        $this->assertSame($expected, Convert::toBool($value));
    }

    /**
     * @return array<string,array{bool|null,mixed}>
     */
    public static function toBoolProvider(): array
    {
        return [
            "''" => [
                false,
                '',
            ],
            "'0'" => [
                false,
                '0',
            ],
            "'1'" => [
                true,
                '1',
            ],
            "'f'" => [
                true,
                'f',
            ],
            "'false'" => [
                false,
                'false',
            ],
            "'n'" => [
                false,
                'n',
            ],
            "'no'" => [
                false,
                'no',
            ],
            "'off'" => [
                false,
                'off',
            ],
            "'on'" => [
                true,
                'on',
            ],
            "'t'" => [
                true,
                't',
            ],
            "'true'" => [
                true,
                'true',
            ],
            "'y'" => [
                true,
                'y',
            ],
            "'yes'" => [
                true,
                'yes',
            ],
        ];
    }

    /**
     * @dataProvider expandTabsProvider
     */
    public function testExpandTabs(
        string $expected,
        string $text,
        int $tabSize = 8,
        int $column = 1
    ): void {
        $this->assertSame(
            $expected,
            Convert::expandTabs($text, $tabSize, $column)
        );
    }

    /**
     * @return array<array{0:string,1:string,2?:int,3?:int}>
     */
    public static function expandTabsProvider(): array
    {
        return [
            ['', '', 4],
            ["\n", "\n", 4],
            ["\n\n", "\n\n", 4],
            ["\n    ", "\n\t", 4],
            ["\n    \n", "\n\t\n", 4],
            ['    ', "\t", 4],
            ['a   ', "a\t", 4],
            ['abcdef  ', "abcdef\t", 4],
            ['abc de  f       ', "abc\tde\tf\t\t", 4],
            ['   ', "\t", 4, 2],
            ['a ', "a\t", 4, 3],
            ['abcdef   ', "abcdef\t", 4, 4],
            ['abc   de  f       ', "abc\tde\tf\t\t", 4, 7],
            ['        ', "\t", 8],
            ['a       ', "a\t", 8],
            ['abcdef  ', "abcdef\t", 8],
            ['abc     de      f               ', "abc\tde\tf\t\t", 8],
            ["   \nabc ", "\t\nabc\t", 4, 2],
            [
                <<<EOF
                    abc de  f       g
                1   23  4
                EOF,
                <<<EOF
                \tabc\tde\tf\t\tg
                1\t23\t4
                EOF,
                4,
            ],
        ];
    }

    /**
     * @dataProvider expandLeadingTabsProvider
     */
    public function testExpandLeadingTabs(
        string $expected,
        string $text,
        int $tabSize = 8,
        bool $preserveLine1 = false,
        int $column = 1
    ): void {
        $this->assertSame(
            $expected,
            Convert::expandLeadingTabs($text, $tabSize, $preserveLine1, $column)
        );
    }

    /**
     * @return array<array{0:string,1:string,2?:int,3?:bool,4?:int}>
     */
    public static function expandLeadingTabsProvider(): array
    {
        return [
            ['', '', 4],
            ["\n", "\n", 4],
            ["\n\n", "\n\n", 4],
            ["\n    ", "\n\t", 4],
            ["\n    \n", "\n\t\n", 4],
            ['    ', "\t", 4],
            ["a\t", "a\t", 4],
            ['    a', "\ta", 4],
            ["    a\t", "\ta\t", 4],
            ["abcdef\t", "abcdef\t", 4],
            ["    abc\tde\tf\t\t", "\tabc\tde\tf\t\t", 4],
            ['   ', "\t", 4, false, 2],
            ['  a', "\ta", 4, false, 3],
            [' abcdef', "\tabcdef", 4, false, 4],
            ["\tabcdef", "\tabcdef", 4, true, 4],
            ["  abc\tde\tf\t\t", "\tabc\tde\tf\t\t", 4, false, 7],
            ['        ', "\t", 8],
            ["a\t", "a\t", 8],
            ['        a', "\ta", 8],
            ["   \nabc\t", "\t\nabc\t", 4, false, 2],
            ["   \n    abc\t", "\t\n\tabc\t", 4, false, 2],
            [
                <<<EOF
                    abc\tde\tf\t\tg
                1\t23\t4
                EOF,
                <<<EOF
                \tabc\tde\tf\t\tg
                1\t23\t4
                EOF,
                4,
            ],
        ];
    }

    public function testParseUrl(): void
    {
        $expected = [
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => 'pass',
                'path' => '/path/;params',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => '',
                'path' => '/path/;params',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => 'pass',
                'path' => '/path/;params',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => '',
                'path' => '/path/;params',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'path' => '/path/;params',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'path' => '/path/;params',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'query' => 'query',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'query' => 'query',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'fragment' => 'fragment',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
            ],
            [
                'scheme' => 'https',
                'host' => 'www.example.com',
                'port' => 123,
                'user' => 'john.doe',
                'path' => '/forum/questions/',
                'query' => 'tag=networking&order=newest',
                'fragment' => 'top',
            ],
            [
                'scheme' => 'ldap',
                'host' => '[2001:db8::7]',
                'path' => '/c=GB',
                'query' => 'objectClass?one',
            ],
            [
                'scheme' => 'mailto',
                'path' => 'John.Doe@example.com',
            ],
            [
                'scheme' => 'news',
                'path' => 'comp.infosystems.www.servers.unix',
            ],
            [
                'scheme' => 'tel',
                'path' => '+1-816-555-1212',
            ],
            [
                'scheme' => 'telnet',
                'host' => '192.0.2.16',
                'port' => 80,
                'path' => '/',
            ],
            [
                'scheme' => 'urn',
                'path' => 'oasis:names:specification:docbook:dtd:xml:4.1.2',
            ],
        ];
        foreach ($this->getUrls(true) as $i => $url) {
            $this->assertSame($expected[$i], Uri::parse($url));
        }
    }

    public function testUnparseUrl(): void
    {
        foreach ($this->getUrls() as $url) {
            $this->assertSame($url, Uri::unparse(parse_url($url)));
        }
        foreach ($this->getUrls(true) as $url) {
            $this->assertSame($url, Uri::unparse(Uri::parse($url)));
        }
    }

    /**
     * @return string[]
     */
    private function getUrls(bool $withParams = false): array
    {
        $params = $withParams ? ';params' : '';

        return [
            "https://user:pass@host:8443/path/$params?query#fragment",
            "https://user:@host:8443/path/$params?query#fragment",
            "https://:pass@host:8443/path/$params?query#fragment",
            "https://:@host:8443/path/$params?query#fragment",
            "https://@host:8443/path/$params?query#fragment",
            "https://host:8443/path/$params?query#fragment",
            'https://host:8443?query',
            'https://host?query',
            'https://host:8443#fragment',
            'https://host#fragment',
            'https://host:8443',
            'https://host',
            // From https://en.wikipedia.org/wiki/Uniform_Resource_Identifier:
            'https://john.doe@www.example.com:123/forum/questions/?tag=networking&order=newest#top',
            'ldap://[2001:db8::7]/c=GB?objectClass?one',
            'mailto:John.Doe@example.com',
            'news:comp.infosystems.www.servers.unix',
            'tel:+1-816-555-1212',
            'telnet://192.0.2.16:80/',
            'urn:oasis:names:specification:docbook:dtd:xml:4.1.2',
        ];
    }

    public function testQueryToData(): void
    {
        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => '',
        ], Convert::queryToData(['key1=value1', 'key2=value2', 'key3=value3', 'key3=', 'key4', '=value5']));
    }

    /**
     * @dataProvider linesToListsProvider
     *
     * @param mixed ...$args
     */
    public function testLinesToLists(string $expected, ...$args): void
    {
        $this->assertSame($expected, Str::eolToNative(Convert::linesToLists(...$args)));
    }

    /**
     * @return array<string,string[]>
     */
    public static function linesToListsProvider(): array
    {
        $defaultRegex = (
            new ReflectionParameter([Convert::class, 'linesToLists'], 'regex')
        )->getDefaultValue();

        $input1 = <<<EOF
            - Before lists

            Section:
            - d
            Other section:
            - <not a letter>
            Without a subsequent list
            Section:
            - a
            - b
            Section:
            - c
            - b
            - d
            EOF;

        $input2 = <<<EOF
            - Before lists
            📍 Section:
            - list item
            - another

            Other section:
            - item i
            - item ii

            - Standalone

            Also standalone

            Section:
            - another
            - and another
            EOF;

        $input3 = <<<EOF
            ### Changes

            - Description

            - Description
              over
              multiple

              ```
              lines
              ```

            ### Changes

            - Description
              with different details

            - Description
              over
              multiple

              ```
              lines
              ```


            EOF;

        $input4 = <<<EOF
            - Description
            - Description
              over
              multiple

              ```
              lines
              ```
            - Description
              with different details
            - Description
              over
              multiple

              ```
              lines
              ```
            EOF;

        return [
            'Default' => [
                <<<EOF
                - Before lists
                Without a subsequent list
                Section:
                - d
                - a
                - b
                - c
                Other section:
                - <not a letter>
                EOF,
                $input1,
            ],
            'Markdown' => [
                <<<EOF
                - Before lists

                Without a subsequent list

                Section:

                - d
                - a
                - b
                - c

                Other section:

                - <not a letter>
                EOF,
                $input1,
                "\n\n",
            ],
            'Nested' => [
                <<<EOF
                - Before lists

                - Without a subsequent list

                - Section:

                  - d
                  - a
                  - b
                  - c

                - Other section:

                  - <not a letter>
                EOF,
                $input1,
                "\n\n",
                '-',
            ],
            'Default (multibyte)' => [
                <<<EOF
                - Before lists
                - Standalone
                📍 Also standalone
                📍 Section:
                  - list item
                  - another
                  - and another
                📍 Other section:
                  - item i
                  - item ii
                EOF,
                $input2,
                "\n",
                '📍',
            ],
            'Markdown (multibyte)' => [
                <<<EOF
                - Before lists
                - Standalone

                📍 Also standalone

                📍 Section:

                  - list item
                  - another
                  - and another

                📍 Other section:

                  - item i
                  - item ii
                EOF,
                $input2,
                "\n\n",
                '📍',
            ],
            'Markdown (multiline #1, loose)' => [
                <<<EOF
                ### Changes

                - Description
                - Description
                  over
                  multiple

                  ```
                  lines
                  ```
                - Description
                  with different details
                EOF,
                $input3,
                "\n\n",
                null,
                $defaultRegex,
                false,
                true,
            ],
            'Markdown (multiline #1, not loose)' => [
                <<<EOF
                - Description
                  over
                  multiple

                  ```
                  lines
                  ```

                ### Changes

                - Description
                - Description
                  with different details
                EOF,
                $input3,
                "\n\n",
            ],
            'Markdown (multiline #2)' => [
                <<<EOF
                - Description
                - Description
                  over
                  multiple

                  ```
                  lines
                  ```
                - Description
                  with different details
                EOF,
                $input4,
                "\n\n",
            ],
        ];
    }

    /**
     * @dataProvider unwrapProvider
     */
    public function testUnwrap(
        string $expected,
        string $string,
        string $break = \PHP_EOL,
        bool $ignoreEscapes = true,
        bool $trimTrailingWhitespace = false,
        bool $collapseBlankLines = false
    ): void {
        $this->assertSame(
            $expected,
            Convert::unwrap($string, $break, $ignoreEscapes, $trimTrailingWhitespace, $collapseBlankLines)
        );
    }

    /**
     * @return array<string,array{0:string,1:string,2?:string,3?:bool,4?:bool,5?:bool}>
     */
    public static function unwrapProvider(): array
    {
        return [
            'empty' => [
                <<<'EOF'
                EOF,
                <<<'EOF'
                EOF,
            ],
            'unwrapped' => [
                <<<'EOF'
                Tempor in mollit ad esse.
                EOF,
                <<<'EOF'
                Tempor in mollit ad esse.
                EOF,
            ],
            'paragraph + list + indent' => [
                <<<'EOF'
                Tempor pariatur nulla esse velit esse:
                - Ad officia ex   reprehenderit sint et.
                - Ea occaecat et aliqua ea officia cupidatat ad nulla cillum.
                - Proident ullamco id eu id.

                Amet duis aliqua qui laboris ullamco dolor nostrud irure commodo ad eu anim enim.

                    Cillum adipisicing sit cillum
                    sunt elit magna fugiat do in
                    deserunt ut Lorem aliqua.


                EOF,
                <<<'EOF'
                Tempor pariatur nulla
                esse velit esse:
                - Ad officia ex
                  reprehenderit sint et.
                - Ea occaecat et aliqua ea officia
                cupidatat ad nulla cillum.
                - Proident ullamco id eu id.

                Amet duis aliqua qui laboris
                ullamco dolor nostrud irure
                commodo ad eu anim enim.

                    Cillum adipisicing sit cillum
                    sunt elit magna fugiat do in
                    deserunt ut Lorem aliqua.


                EOF,
            ],
            'leading + trailing + inner lines' => [
                <<<'EOF'
                 Est   esse sunt velit ea. 
                EOF,
                <<<'EOF'

                Est   esse
                sunt velit
                ea.

                EOF,
            ],
            'escaped #1' => [
                <<<'EOF'
                Nisi aliqua id in cupidatat\ consectetur irure ad nisi Lorem non ea reprehenderit id eu.
                EOF,
                <<<'EOF'
                Nisi aliqua id in cupidatat\
                consectetur irure ad nisi
                Lorem non ea reprehenderit id eu.
                EOF,
            ],
            'escaped #2' => [
                <<<'EOF'
                Nisi aliqua id in cupidatat\
                consectetur irure ad nisi Lorem non ea reprehenderit id eu.
                EOF,
                <<<'EOF'
                Nisi aliqua id in cupidatat\
                consectetur irure ad nisi
                Lorem non ea reprehenderit id eu.
                EOF,
                \PHP_EOL,
                false,
            ],
            'trimmed #1 (baseline)' => [
                <<<'EOF'
                Est magna\  voluptate  minim est.

                 


                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
            ],
            'trimmed #2 (+ trimTrailingWhitespace)' => [
                <<<'EOF'
                Est magna\ voluptate minim est.




                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
                true,
                true,
            ],
            'trimmed #3 (- ignoreEscapes)' => [
                <<<'EOF'
                Est magna\  voluptate minim est.




                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
                false,
                true,
            ],
            'trimmed #4 (+ collapseBlankLines)' => [
                <<<'EOF'
                Est magna\  voluptate minim est.


                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
                false,
                true,
                true,
            ],
        ];
    }

    public function testToShellArg(): void
    {
        $this->assertSame("''", Convert::toShellArg(''));
        $this->assertSame('abc', Convert::toShellArg('abc'));
        $this->assertSame('/some/path', Convert::toShellArg('/some/path'));
        $this->assertSame("'/some/path with spaces'", Convert::toShellArg('/some/path with spaces'));
        $this->assertSame("''\''quotable'\'' \"quotes\"'", Convert::toShellArg('\'quotable\' "quotes"'));
    }

    /**
     * @dataProvider toNormalProvider
     */
    public function testToNormal(
        string $expected,
        string $text
    ): void {
        $this->assertSame($expected, Convert::toNormal($text));
    }

    /**
     * @return array<string[]>
     */
    public static function toNormalProvider(): array
    {
        return [
            ['HISTORY AND GEOGRAPHY', 'History & Geography'],
            ['MATHEMATICS', '& Mathematics'],
            ['LANGUAGES MODERN', 'Languages — Modern'],
            ['IT', 'I.T.'],
            ['IT', 'IT. '],
            ['IT', 'it'],
        ];
    }

    /**
     * @dataProvider dataToQueryProvider
     *
     * @param mixed[] $data
     */
    public function testDataToQuery(
        string $expected,
        array $data,
        bool $preserveKeys = false,
        ?DateFormatter $dateFormatter = null
    ): void {
        $this->assertSame($expected, Convert::dataToQuery($data, $preserveKeys, $dateFormatter));
    }

    /**
     * @return array<array{string,mixed[],2?:bool,3?:DateFormatter}>
     */
    public static function dataToQueryProvider(): array
    {
        $data = [
            'user_id' => 7654,
            'fields' => [
                'surname' => 'Williams',
                'email' => 'JWilliams432@gmail.com',
                'notify_by' => [
                    'email',
                    'sms',
                ],
                'created' => new DateTimeImmutable('2021-10-02T17:23:14+10:00'),
            ],
        ];

        return [
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][0]=email&fields[notify_by][1]=sms&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D=email&fields%5Bnotify_by%5D%5B1%5D=sms&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
                true,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=Sat, 02 Oct 2021 17:23:14 +1000
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=Sat%2C%2002%20Oct%202021%2017%3A23%3A14%20%2B1000',
                $data,
                false,
                new DateFormatter(DateTimeInterface::RSS),
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=2021-10-02T07:23:14+00:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=2021-10-02T07%3A23%3A14%2B00%3A00',
                $data,
                false,
                new DateFormatter(DateTimeInterface::ATOM, 'UTC'),
            ],
        ];
    }

    /**
     * @dataProvider valueToCodeProvider
     *
     * @param mixed $value
     */
    public function testValueToCode(
        string $expected,
        $value,
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    '
    ): void {
        $this->assertSame($expected, Convert::valueToCode($value, $delimiter, $arrow, $escapeCharacters, $tab));
    }

    /**
     * @return array<string,array{string,mixed,2?:string,3?:string,4?:string|null,5?:string}>
     */
    public static function valueToCodeProvider(): array
    {
        $array = [
            'list1' => [1, 2.0, 3.14],
            'list2' => [1],
            'empty' => [],
            'index' => [5 => 'a', 9 => 'b', 2 => 'c'],
            "multiline\nkey" => 'This string has "double quotes", \'single quotes\', and commas.',
            'bool1' => true,
            'bool2' => false,
        ];

        return [
            'default' => [
                <<<'EOF'
                ['list1' => [1, 2.0, 3.14], 'list2' => [1], 'empty' => [], 'index' => [5 => 'a', 9 => 'b', 2 => 'c'], "multiline\nkey" => 'This string has "double quotes", \'single quotes\', and commas.', 'bool1' => true, 'bool2' => false]
                EOF,
                $array,
            ],
            'compact' => [
                <<<'EOF'
                ['list1'=>[1,2.0,3.14],'list2'=>[1],'empty'=>[],'index'=>[5=>'a',9=>'b',2=>'c'],"multiline\nkey"=>'This string has "double quotes", \'single quotes\', and commas.','bool1'=>true,'bool2'=>false]
                EOF,
                $array,
                ',',
                '=>',
            ],
            'multiline' => [
                <<<'EOF'
                [
                    'list1' => [
                        1,
                        2.0,
                        3.14,
                    ],
                    'list2' => [
                        1,
                    ],
                    'empty' => [],
                    'index' => [
                        5 => 'a',
                        9 => 'b',
                        2 => 'c',
                    ],
                    "multiline\nkey" => 'This string has "double quotes", \'single quotes\', and commas.',
                    'bool1' => true,
                    'bool2' => false,
                ]
                EOF,
                $array,
                ',' . \PHP_EOL,
            ],
            'escaped commas' => [
                <<<'EOF'
                ['list1' => [1, 2.0, 3.14], 'list2' => [1], 'empty' => [], 'index' => [5 => 'a', 9 => 'b', 2 => 'c'], "multiline\nkey" => "This string has "double quotes"\x2c 'single quotes'\x2c and commas.", 'bool1' => true, 'bool2' => false]
                EOF,
                $array,
                ', ',
                ' => ',
                ',',
            ],
        ];
    }
}
