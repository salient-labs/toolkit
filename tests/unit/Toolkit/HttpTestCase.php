<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Core\FileDescriptor;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\Process;
use Salient\Http\HttpHeaders;
use Salient\Utility\File;
use Salient\Utility\Str;

abstract class HttpTestCase extends TestCase
{
    protected const HTTP_SERVER_HOST = 'localhost';
    protected const HTTP_SERVER_PORT = '3007';
    protected const HTTP_SERVER_AUTHORITY = self::HTTP_SERVER_HOST . ':' . self::HTTP_SERVER_PORT;
    protected const HTTP_SERVER_URI = 'http://' . self::HTTP_SERVER_AUTHORITY;

    protected const HTTP_HEADER_IGNORE_LIST = [
        Header::ACCEPT_ENCODING,
        Header::USER_AGENT,
    ];

    private string $ResponseDir;
    private Process $HttpServer;

    /**
     * Assert that the given HTTP message lists are the same after normalising
     * line endings, removing ignored headers and sorting the remaining headers
     *
     * @param string[] $expected
     * @param string[] $actual
     * @param string[] $ignore
     */
    public static function assertSameHttpMessages(
        array $expected,
        array $actual,
        array $ignore = self::HTTP_HEADER_IGNORE_LIST,
        string $message = ''
    ): void {
        static::assertSameSize($expected, $actual, $message);
        foreach ($expected as $expectedMessage) {
            /** @var string */
            $actualMessage = array_shift($actual);
            static::assertSameHttpMessage($expectedMessage, $actualMessage, $ignore, $message);
        }
    }

    /**
     * Assert that the given HTTP messages are the same after normalising line
     * endings, removing ignored headers and sorting the remaining headers
     *
     * @param string[] $ignore
     */
    public static function assertSameHttpMessage(
        string $expected,
        string $actual,
        array $ignore = self::HTTP_HEADER_IGNORE_LIST,
        string $message = ''
    ): void {
        $expected = str_replace(
            ['{{HTTP_SERVER_AUTHORITY}}', '{{HTTP_SERVER_URI}}'],
            [self::HTTP_SERVER_AUTHORITY, self::HTTP_SERVER_URI],
            Str::setEol($expected, "\r\n"),
        );

        $actual = explode("\r\n\r\n", $actual, 2);
        $headers[] = explode("\r\n", $actual[0], 2)[0];
        foreach (HttpHeaders::from($actual[0])->except($ignore)->sort()->getHeaders() as $name => $values) {
            $headers[] = sprintf('%s: %s', $name, implode(', ', $values));
        }
        $actual[0] = implode("\r\n", $headers);
        $actual = implode("\r\n\r\n", $actual);
        static::assertEquals($expected, $actual, $message);
    }

    /**
     * @param HttpResponseInterface|string ...$responses
     */
    protected function startHttpServer(...$responses): Process
    {
        $this->stopHttpServer();

        if ($responses === [] || count($responses) > 1) {
            $args[] = '--';
            if ($responses !== []) {
                $dir = File::createTempDir();
                foreach ($responses as $i => $response) {
                    $file = sprintf('%s/%d.http', $dir, $i);
                    File::writeContents($file, $this->filterResponse($response));
                    $args[] = $file;
                }
                $this->ResponseDir = $dir;
            }
        } else {
            $input = $this->filterResponse($responses[0]);
        }

        $process = new Process([
            ...self::PHP_COMMAND,
            self::getPackagePath() . '/tests/unit/Toolkit/http-server.php',
            self::HTTP_SERVER_AUTHORITY,
            '-1',
            ...($args ?? [])
        ], $input ?? '');

        $process->start();

        while ($process->poll()->isRunning()) {
            if (strpos(
                $process->getOutput(FileDescriptor::ERR),
                'Server started at ' . self::HTTP_SERVER_URI
            ) !== false) {
                return $this->HttpServer = $process;
            }
        }

        throw new RuntimeException(sprintf(
            "Error starting HTTP server (status: %d; stdout: '%s'; stderr: '%s')",
            $process->getExitStatus(),
            $process->getOutput(),
            $process->getOutput(FileDescriptor::ERR),
        ));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->stopHttpServer();
    }

    private function stopHttpServer(): void
    {
        if (isset($this->HttpServer)) {
            while ($this->HttpServer->isRunning()) {
                $this->HttpServer->stop();
            }
            unset($this->HttpServer);
        }

        if (isset($this->ResponseDir)) {
            if (is_dir($this->ResponseDir)) {
                File::pruneDir($this->ResponseDir, true);
            }
            unset($this->ResponseDir);
        }
    }

    /**
     * @param HttpResponseInterface|string $response
     */
    private function filterResponse($response): string
    {
        if (
            $response instanceof HttpResponseInterface
            && !$response->hasHeader(Header::CONTENT_LENGTH)
            && ($size = $response->getBody()->getSize()) !== null
        ) {
            $response = $response->withHeader(Header::CONTENT_LENGTH, (string) $size);
        }

        return (string) $response;
    }
}
