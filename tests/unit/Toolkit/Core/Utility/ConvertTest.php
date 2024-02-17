<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Lkrms\Http\Uri;
use Lkrms\Support\Date\DateFormatter;
use Lkrms\Tests\TestCase;
use Salient\Core\Catalog\QueryFlag;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;
use DateTimeImmutable;
use DateTimeInterface;

final class ConvertTest extends TestCase
{
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
            'key4' => '',
        ], Get::filter(['key1=value1', 'key2=value2', 'key3=value3', 'key3=', 'key4', '=value5']));
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
            Str::unwrap($string, $break, $ignoreEscapes, $trimTrailingWhitespace, $collapseBlankLines)
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

    /**
     * @dataProvider toNormalProvider
     */
    public function testToNormal(
        string $expected,
        string $text
    ): void {
        $this->assertSame($expected, Str::normalise($text));
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
     * @param int-mask-of<QueryFlag::*> $flags
     */
    public function testDataToQuery(
        string $expected,
        array $data,
        int $flags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        ?DateFormatter $dateFormatter = null
    ): void {
        $this->assertSame($expected, Get::query($data, $flags, $dateFormatter));
    }

    /**
     * @return array<array{string,mixed[],2?:int-mask-of<QueryFlag::*>,3?:DateFormatter}>
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
                QueryFlag::PRESERVE_ALL_KEYS,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=Sat, 02 Oct 2021 17:23:14 +1000
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=Sat%2C%2002%20Oct%202021%2017%3A23%3A14%20%2B1000',
                $data,
                QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::RSS),
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=2021-10-02T07:23:14+00:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=2021-10-02T07%3A23%3A14%2B00%3A00',
                $data,
                QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::ATOM, 'UTC'),
            ],
        ];
    }
}
