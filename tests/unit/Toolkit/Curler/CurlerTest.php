<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use Salient\Contract\Core\MimeType;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Core\Utility\Json;
use Salient\Core\Process;
use Salient\Curler\Exception\CurlerCurlErrorException;
use Salient\Curler\Exception\CurlerHttpErrorException;
use Salient\Curler\Curler;
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
        $server = $this->getJsonServer(self::OUT);
        $this->assertSame(self::OUT, $this->getCurler('/foo')->post(self::IN, self::QUERY));
        $this->assertSameHttpMessage(
            <<<EOF
            POST /foo?quux=1 HTTP/1.1
            Host: {{HTTP_SERVER_AUTHORITY}}
            Accept: application/json
            Content-Type: application/json
            Content-Length: 13

            {"baz":"qux"}
            EOF,
            $server->getOutput(),
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
     * @param mixed[] $data
     */
    private function getJsonServer(array $data): Process
    {
        return $this->startHttpServer(
            new HttpResponse(
                200,
                null,
                Json::stringify($data),
                [Header::CONTENT_TYPE => MimeType::JSON],
            ),
        );
    }

    private static function getCurler(string $endpoint = ''): Curler
    {
        return (new Curler(self::HTTP_SERVER_URI . $endpoint));
    }
}
