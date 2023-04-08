<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Facade\Convert;
use UnexpectedValueException;

final class ConversionsTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider toBoolOrNullProvider
     */
    public function testToBoolOrNull($value, $expected)
    {
        $this->assertSame($expected, Convert::toBoolOrNull($value));
    }

    public static function toBoolOrNullProvider()
    {
        return [
            "''" => [
                '',
                false,
            ],
            "'0'" => [
                '0',
                false,
            ],
            "'1'" => [
                '1',
                true,
            ],
            "'f'" => [
                'f',
                false,
            ],
            "'false'" => [
                'false',
                false,
            ],
            "'n'" => [
                'n',
                false,
            ],
            "'no'" => [
                'no',
                false,
            ],
            "'off'" => [
                'off',
                false,
            ],
            "'on'" => [
                'on',
                true,
            ],
            "'t'" => [
                't',
                true,
            ],
            "'true'" => [
                'true',
                true,
            ],
            "'y'" => [
                'y',
                true,
            ],
            "'yes'" => [
                'yes',
                true,
            ],
        ];
    }

    /**
     * @dataProvider flattenProvider
     */
    public function testFlatten($value, $expected)
    {
        $this->assertSame($expected, Convert::flatten($value));
    }

    public static function flattenProvider()
    {
        return [
            [
                [[['id' => 1]]],
                ['id' => 1],
            ],
            [
                ['nested scalar'],
                'nested scalar',
            ],
            [
                ['nested associative' => 1],
                ['nested associative' => 1],
            ],
            [
                [[1, 'links' => [2, 3]]],
                [1, 'links' => [2, 3]],
            ],
            [
                'plain scalar',
                'plain scalar',
            ],
        ];
    }

    public function testArrayKeyToOffset()
    {
        $data = [
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ];

        $this->assertSame(0, Convert::arrayKeyToOffset('a', $data));
        $this->assertSame(1, Convert::arrayKeyToOffset('b', $data));
        $this->assertSame(2, Convert::arrayKeyToOffset('A', $data));
        $this->assertSame(3, Convert::arrayKeyToOffset('B', $data));
        $this->assertNull(Convert::arrayKeyToOffset('c', $data));
    }

    public function testArraySpliceAtKey()
    {
        $data1 = $data2 = $data3 = $data4 = $data5 = [
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ];

        $slice = Convert::getInstance()->arraySpliceAtKey($data1, 'b');
        $this->assertSame([
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ], $slice);
        $this->assertSame([
            'a' => 'value0',
        ], $data1);

        $slice = Convert::getInstance()->arraySpliceAtKey($data2, 'A', 1, ['A2' => 10]);
        $this->assertSame([
            'A' => 'value2',
        ], $slice);
        $this->assertSame([
            'a' => 'value0',
            'b' => 'value1',
            'A2' => 10,
            'B' => 'value3',
        ], $data2);

        $slice = Convert::getInstance()->arraySpliceAtKey($data3, 'B', 0, ['a' => 20]);
        $this->assertSame([], $slice);
        $this->assertSame([
            'a' => 20,
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ], $data3);

        $slice = Convert::getInstance()->arraySpliceAtKey($data4, 'B', 0, ['A2' => 10]);
        $this->assertSame([], $slice);
        $this->assertSame([
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'A2' => 10,
            'B' => 'value3',
        ], $data4);

        $this->expectException(UnexpectedValueException::class);
        $slice = Convert::getInstance()->arraySpliceAtKey($data5, 'c', 2);
    }

    public function testRenameArrayKey()
    {
        $data = [
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ];

        $this->assertSame([
            'a' => 'value0',
            'b_2' => 'value1',
            'A' => 'value2',
            'B' => 'value3',
        ], Convert::renameArrayKey('b', 'b_2', $data));

        $this->assertSame([
            'a' => 'value0',
            'b' => 'value1',
            'A' => 'value2',
            0 => 'value3',
        ], Convert::renameArrayKey('B', 0, $data));

        $this->expectException(UnexpectedValueException::class);
        $slice = Convert::renameArrayKey('c', 2, $data);
    }

    public function testResolvePath()
    {
        $this->assertEquals('/dir/subdir2/doc', Convert::resolvePath('/dir/subdir/files/../../subdir2/./doc'));
    }

    public function testResolveRelativeUrl()
    {
        // From [RFC1808] Section 5
        $baseUrl = 'http://a/b/c/d;p?q#f';
        // "Normal Examples"
        $relativeUrls = [
            'g:h' => 'g:h',
            'g' => 'http://a/b/c/g',
            './g' => 'http://a/b/c/g',
            'g/' => 'http://a/b/c/g/',
            '/g' => 'http://a/g',
            '//g' => 'http://g',
            '?y' => 'http://a/b/c/d;p?y',
            'g?y' => 'http://a/b/c/g?y',
            'g?y/./x' => 'http://a/b/c/g?y/./x',
            '#s' => 'http://a/b/c/d;p?q#s',
            'g#s' => 'http://a/b/c/g#s',
            'g#s/./x' => 'http://a/b/c/g#s/./x',
            'g?y#s' => 'http://a/b/c/g?y#s',
            ';x' => 'http://a/b/c/d;x',
            'g;x' => 'http://a/b/c/g;x',
            'g;x?y#s' => 'http://a/b/c/g;x?y#s',
            '.' => 'http://a/b/c/',
            './' => 'http://a/b/c/',
            '..' => 'http://a/b/',
            '../' => 'http://a/b/',
            '../g' => 'http://a/b/g',
            '../..' => 'http://a/',
            '../../' => 'http://a/',
            '../../g' => 'http://a/g',

            // "Abnormal Examples"
            '' => 'http://a/b/c/d;p?q#f',
            '../../../g' => 'http://a/../g',
            '../../../../g' => 'http://a/../../g',
            '/./g' => 'http://a/./g',
            '/../g' => 'http://a/../g',
            'g.' => 'http://a/b/c/g.',
            '.g' => 'http://a/b/c/.g',
            'g..' => 'http://a/b/c/g..',
            '..g' => 'http://a/b/c/..g',
            './../g' => 'http://a/b/g',
            './g/.' => 'http://a/b/c/g/',
            'g/./h' => 'http://a/b/c/g/h',
            'g/../h' => 'http://a/b/c/h',
            'http:g' => 'http:g',
            'http:' => 'http:',
        ];
        foreach ($relativeUrls as $url => $expected) {
            $this->assertSame($expected, Convert::resolveRelativeUrl($url, $baseUrl));
        }
    }

    public function testParseUrl()
    {
        $expected = [
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => 'pass',
                'path' => '/path/',
                'query' => 'query',
                'fragment' => 'fragment',
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => '',
                'path' => '/path/',
                'query' => 'query',
                'fragment' => 'fragment',
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => 'pass',
                'path' => '/path/',
                'query' => 'query',
                'fragment' => 'fragment',
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => '',
                'path' => '/path/',
                'query' => 'query',
                'fragment' => 'fragment',
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'path' => '/path/',
                'query' => 'query',
                'fragment' => 'fragment',
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'path' => '/path/',
                'query' => 'query',
                'fragment' => 'fragment',
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'params' => 'params',
            ],
            [
                'scheme' => 'https',
                'host' => 'host',
                'params' => 'params',
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
            $this->assertSame($expected[$i], Convert::parseUrl($url));
        }
    }

    public function testUnparseUrl()
    {
        foreach ($this->getUrls() as $url) {
            $this->assertSame($url, Convert::unparseUrl(parse_url($url)));
        }
        foreach ($this->getUrls(true) as $url) {
            $this->assertSame($url, Convert::unparseUrl(Convert::parseUrl($url)));
        }
    }

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
            ...(!$withParams ? [] : [
                'https://host:8443;params',
                'https://host;params',
            ]),
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

    public function testIterableToItem()
    {
        $data = [
            [
                'id' => 10,
                'name' => 'A',
            ],
            [
                'id' => 27,
                'name' => 'B',
            ],
            [
                'id' => 8,
                'name' => 'C',
            ],
            [
                'id' => 8,
                'name' => 'D',
            ],
            [
                'id' => 72,
                'name' => 'E',
            ],
            [
                'id' => 21,
                'name' => 'F',
            ],
        ];

        $iteratorFactory = function () use ($data) {
            foreach ($data as $record) {
                yield $record;
            }
        };

        $iterator = $iteratorFactory();
        $this->assertSame(['id' => 27, 'name' => 'B'], Convert::iterableToItem($iterator, 'id', 27));
        $this->assertSame(['id' => 8, 'name' => 'C'], Convert::iterableToItem($iterator, 'id', 8));
        $this->assertSame(['id' => 8, 'name' => 'D'], Convert::iterableToItem($iterator, 'id', 8));
        $this->assertSame(['id' => 21, 'name' => 'F'], Convert::iterableToItem($iterator, 'id', 21));
        $this->assertSame(false, Convert::iterableToItem($iterator, 'id', 8));

        $iterator = $iteratorFactory();
        $this->assertSame(['id' => 10, 'name' => 'A'], Convert::iterableToItem($iterator, 'id', 10));
    }

    public function testQueryToData()
    {
        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => '',
        ], Convert::queryToData(['key1=value1', 'key2=value2', 'key3=value3', 'key3=', 'key4', '=value5']));
    }

    public function testToShellArg()
    {
        $this->assertSame("''", Convert::toShellArg(''));
        $this->assertSame('abc', Convert::toShellArg('abc'));
        $this->assertSame('/some/path', Convert::toShellArg('/some/path'));
        $this->assertSame("'/some/path with spaces'", Convert::toShellArg('/some/path with spaces'));
        $this->assertSame("''\\''quotable'\\'' \"quotes\"'", Convert::toShellArg('\'quotable\' "quotes"'));
    }
}
