<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use Salient\Cache\CacheStore;
use Salient\Contract\Core\MimeType;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Contract\Http\HttpRequestMethod as Method;
use Salient\Core\Facade\Cache;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Http;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Str;
use Salient\Core\Process;
use Salient\Curler\Contract\ICurlerPage;
use Salient\Curler\Contract\ICurlerPager;
use Salient\Curler\Exception\CurlerCurlErrorException;
use Salient\Curler\Exception\CurlerHttpErrorException;
use Salient\Curler\Support\CurlerPage;
use Salient\Curler\Curler;
use Salient\Curler\CurlerFile;
use Salient\Http\HttpHeaders;
use Salient\Http\HttpResponse;
use Salient\Tests\HttpTestCase;

/**
 * @covers \Salient\Curler\Curler
 */
final class CurlerTest extends HttpTestCase
{
    private const QUERY = ['quux' => 1];
    private const IN = ['baz' => 'qux'];
    private const OUT = ['foo' => 'bar'];
    private const OUT_PAGES = [[['name' => 'foo'], ['name' => 'bar'], ['name' => 'baz']], [['name' => 'qux']]];

    public function testGet(): void
    {
        $server = $this->getJsonServer(self::OUT);
        $this->assertSame(self::OUT, $this->getCurler('/foo')->get(self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            GET /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json


            EOF,
            $server->getOutput(),
        );
    }

    public function testHead(): void
    {
        $server = $this->getJsonServer(self::OUT);
        $this->assertSame([
            'content-type' => [MimeType::JSON],
            'content-length' => ['13'],
        ], $this->getCurler('/foo')->head(self::QUERY)->all());
        $this->assertSameHttpMessage(
            <<<EOF
            HEAD /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json


            EOF,
            $server->getOutput(),
        );
    }

    public function testPost(): void
    {
        $this->doTestPost();
    }

    public function testPut(): void
    {
        $this->doTestPost(Method::PUT);
    }

    public function testPatch(): void
    {
        $this->doTestPost(Method::PATCH);
    }

    public function testDelete(): void
    {
        $this->doTestPost(Method::DELETE);
    }

    private function doTestPost(string $method = Method::POST): void
    {
        $server = $this->getJsonServer(self::OUT, self::OUT, self::OUT);
        $m = Str::lower($method);

        $this->assertSame(self::OUT, $this->getCurler('/foo')->{$m}(self::IN, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            {$method} /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json
            Content-Length: 13
            Content-Type: application/json

            {"baz":"qux"}
            EOF,
            $server->getOutput(),
        );

        $this->assertSame(self::OUT, $this->getCurler('/foo')->{$m}(null, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            {$method} /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json
            Content-Length: 0
            Content-Type: application/x-www-form-urlencoded


            EOF,
            $server->getNewOutput(),
        );

        $file = self::getFixturesPath(__CLASS__) . '/profile.gif';
        $data = self::IN + [
            'attachment' => new CurlerFile($file),
        ];
        $curler = $this->getCurler('/foo');
        $result = $curler->{$m}($data, self::QUERY);
        $output = $server->getNewOutput();
        $boundaryParam = HttpHeaders::from($output)->getMultipartBoundary();
        $this->assertNotNull($boundaryParam);
        $boundary = Http::unquoteString($boundaryParam);
        $content = File::getContents($file);
        $length = 169 + strlen($content) + 3 * strlen($boundary);
        $this->assertSame(self::OUT, $result);
        $this->assertSameHttpMessage(
            <<<EOF
            {$method} /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json
            Content-Length: {$length}
            Content-Type: multipart/form-data; boundary={$boundaryParam}

            --{$boundary}
            Content-Disposition: form-data; name="baz"

            qux
            --{$boundary}
            Content-Disposition: form-data; name="attachment"; filename="profile.gif"
            Content-Type: image/gif

            {$content}
            --{$boundary}--

            EOF,
            $output,
        );
    }

    public function testGetP(): void
    {
        $this->doTestGetP();
    }

    public function testPostP(): void
    {
        $this->doTestGetP(Method::POST);
    }

    public function testPutP(): void
    {
        $this->doTestGetP(Method::PUT);
    }

    public function testPatchP(): void
    {
        $this->doTestGetP(Method::PATCH);
    }

    public function testDeleteP(): void
    {
        $this->doTestGetP(Method::DELETE);
    }

    private function doTestGetP(string $method = Method::GET): void
    {
        $server = $this->getJsonServer(...self::OUT_PAGES);
        $output = [];
        $m = Str::lower($method) . 'P';

        $pager = $this->createMock(ICurlerPager::class);
        $pager->method('prepareQuery')->willReturnArgument(0);
        $pager->method('prepareCurler')->willReturnArgument(0);
        $pager->method('prepareData')->willReturnArgument(0);
        $pager
            ->expects($this->exactly(2))
            ->method('getPage')
            ->willReturnCallback(
                function (
                    $data,
                    Curler $curler,
                    ?ICurlerPage $previousPage = null
                ) use ($server, &$output): CurlerPage {
                    $output[] = $server->getNewOutput();
                    return new CurlerPage(
                        $data,
                        $curler,
                        $previousPage,
                        $previousPage
                            ? null
                            : $curler->getQueryUrl(['page' => 2])
                    );
                }
            );

        $curler = $this->getCurler('/foo')->withPager($pager);
        $args = $method === Method::GET ? [] : [self::IN];
        $body = $method === Method::GET ? '' : '{"baz":"qux"}';
        $headers = $method === Method::GET ? '' : <<<EOF

            Content-Length: 13
            Content-Type: application/json
            EOF;
        $result = iterator_to_array($curler->{$m}(...$args), false);
        $this->assertSame(Arr::flatten(self::OUT_PAGES, 1), $result);
        $this->assertSameHttpMessages([
            <<<EOF
            {$method} /foo HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json{$headers}

            {$body}
            EOF,
            <<<EOF
            {$method} /foo?page=2 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json{$headers}

            {$body}
            EOF,
        ], $output);
    }

    public function testPostR(): void
    {
        $this->doTestPostR();
    }

    public function testPutR(): void
    {
        $this->doTestPostR(Method::PUT);
    }

    public function testPatchR(): void
    {
        $this->doTestPostR(Method::PATCH);
    }

    public function testDeleteR(): void
    {
        $this->doTestPostR(Method::DELETE);
    }

    private function doTestPostR(string $method = Method::POST): void
    {
        $server = $this->getJsonServer(self::OUT);
        $m = 'raw' . Str::upperFirst(Str::lower($method));

        $file = self::getFixturesPath(__CLASS__) . '/profile.gif';
        $content = File::getContents($file);
        $length = strlen($content);
        $curler = $this->getCurler('/foo')->with('ExpectJson', false);
        $this->assertSame(self::OUT, $curler->{$m}($content, 'image/gif', self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            {$method} /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: */*
            Content-Length: {$length}
            Content-Type: image/gif

            {$content}
            EOF,
            $server->getOutput(),
        );
    }

    public function testCaching(): void
    {
        $server = $this->getJsonServer(self::OUT, [], self::OUT, [], self::OUT);
        $cache = new CacheStore();
        Cache::load($cache);

        try {
            $curler = $this
                ->getCurler('/foo')
                ->with('UserAgent', sprintf('%s/1.0.0', __FUNCTION__))
                ->with('CacheResponse', true);
            $this->assertSame(self::OUT, $curler->get(self::QUERY));
            $this->assertSameHttpMessage(
                $request = <<<EOF
                    GET /foo?quux=1 HTTP/1.1
                    Host: {{HTTP_SERVER_AUTHORITY}}
                    Accept: application/json


                    EOF,
                $server->getOutput(),
            );
            $this->assertSame(self::OUT, $curler->get(self::QUERY));
            $this->assertSame('', $server->getNewOutput());

            $curler = $curler->with('Flush', true);
            $this->assertSame([], $curler->get(self::QUERY));
            $this->assertSameHttpMessage($request, $server->getNewOutput());
            $curler = $curler->with('Flush', false);
            $this->assertSame([], $curler->get(self::QUERY));
            $this->assertSame('', $server->getNewOutput());

            $this->assertCount(1, $cacheKeys = $cache->getAllKeys());
            $this->assertSame(self::OUT, $curler->post(self::IN, self::QUERY));
            $this->assertSameHttpMessage(
                $request = <<<EOF
                    POST /foo?quux=1 HTTP/1.1
                    Host: {{HTTP_SERVER_AUTHORITY}}
                    Accept: application/json
                    Content-Length: 13
                    Content-Type: application/json

                    {"baz":"qux"}
                    EOF,
                $server->getNewOutput(),
            );
            $this->assertSame($cacheKeys, $cache->getAllKeys());

            $curler = $curler->with('CachePostResponse', true);
            $this->assertSame([], $curler->post(self::IN, self::QUERY));
            $this->assertSameHttpMessage($request, $server->getNewOutput());
            $this->assertSame([], $curler->post(self::IN, self::QUERY));
            $this->assertSame('', $server->getNewOutput());
            // Different data = miss
            $this->assertSame(self::OUT, $curler->post([], self::QUERY));
            $this->assertSameHttpMessage(
                <<<EOF
                POST /foo?quux=1 HTTP/1.1
                Host: {{HTTP_SERVER_AUTHORITY}}
                Accept: application/json
                Content-Length: 2
                Content-Type: application/json

                []
                EOF,
                $server->getNewOutput(),
            );
            $this->assertSame([
                Curler::class . ':response:GET:http%3A%2F%2Flocalhost%3A3007%2Ffoo%3Fquux%3D1:e3b031126bf034cbe4d43a69e4cdba43',
                Curler::class . ':response:POST:http%3A%2F%2Flocalhost%3A3007%2Ffoo%3Fquux%3D1:24019ed5d14784ec817eec6ed1ec38f1',
                Curler::class . ':response:POST:http%3A%2F%2Flocalhost%3A3007%2Ffoo%3Fquux%3D1:97d09f76c4d19c288d5c81c866058962',
            ], $cache->getAllKeys());
            $this->assertCount(0, $cache->asOfNow(time() + 3601)->getAllKeys());
        } finally {
            Cache::unload();
        }
    }

    public function testCurlError(): void
    {
        $this->expectException(CurlerCurlErrorException::class);
        (new Curler('//localhost'))->get();
    }

    public function testHttpError(): void
    {
        $this->startHttpServer(new HttpResponse(404));
        $this->expectException(CurlerHttpErrorException::class);
        $this->expectExceptionMessage('HTTP error 404');
        $this->getCurler()->get();
    }

    /**
     * @param mixed[] ...$data
     */
    private function getJsonServer(array ...$data): Process
    {
        foreach ($data as $data) {
            $responses[] = new HttpResponse(
                200,
                Json::stringify($data),
                [Header::CONTENT_TYPE => MimeType::JSON],
            );
        }
        return $this->startHttpServer(...($responses ?? []));
    }

    private static function getCurler(string $endpoint = ''): Curler
    {
        return (new Curler(self::HTTP_SERVER_URI . $endpoint));
    }
}
