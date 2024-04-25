<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use Salient\Contract\Core\MimeType;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Http;
use Salient\Core\Utility\Json;
use Salient\Core\Process;
use Salient\Curler\Exception\CurlerCurlErrorException;
use Salient\Curler\Exception\CurlerHttpErrorException;
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
        $server = $this->getJsonServer(self::OUT, self::OUT, self::OUT);

        $this->assertSame(self::OUT, $this->getCurler('/foo')->post(self::IN, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            POST /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json
            Content-Length: 13
            Content-Type: application/json

            {"baz":"qux"}
            EOF,
            $server->getOutput(),
        );

        $this->assertSame(self::OUT, $this->getCurler('/foo')->post(null, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            POST /foo?quux=1 HTTP/1.1
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
        $result = $curler->post($data, self::QUERY);
        $output = $server->getNewOutput();
        $boundaryParam = HttpHeaders::from($output)->getMultipartBoundary();
        $this->assertNotNull($boundaryParam);
        $boundary = Http::unquoteString($boundaryParam);
        $content = File::getContents($file);
        $length = 169 + strlen($content) + 3 * strlen($boundary);
        $this->assertSame(self::OUT, $result);
        $this->assertSameHttpMessage(
            <<<EOF
            POST /foo?quux=1 HTTP/1.1
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
