<?php declare(strict_types=1);

namespace Lkrms\Support\Http;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\CurlerHeadersFlag;
use Lkrms\Facade\Console;
use Lkrms\Support\Dictionary\HttpRequestMethods;
use Lkrms\Support\Http\HttpRequest;
use Lkrms\Support\Http\HttpResponse;
use RuntimeException;

/**
 * Listen for HTTP requests on a local address
 *
 * @property-read string $Host
 * @property-read int $Port
 * @property-read int $Timeout
 */
final class HttpServer implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Host;

    /**
     * @var int
     */
    protected $Port;

    /**
     * @var int
     */
    protected $Timeout;

    /**
     * @var resource|null
     */
    private $Server;

    public function __construct(string $host, int $port, int $timeout = 300)
    {
        $this->Host    = $host;
        $this->Port    = $port;
        $this->Timeout = $timeout;
    }

    /**
     * @return $this
     */
    public function start()
    {
        if ($this->Server) {
            return $this;
        }

        $errMessage = $errCode = null;
        if ($server = stream_socket_server(
            "tcp://{$this->Host}:{$this->Port}", $errCode, $errMessage
        )) {
            $this->Server = $server;

            return $this;
        }

        throw new RuntimeException(sprintf(
            'Unable to start HTTP server at %s:%d (error %d: %s)',
            $this->Host,
            $this->Port,
            $errCode,
            $errMessage
        ));
    }

    /**
     * @return $this
     */
    public function stop()
    {
        if ($this->Server) {
            fclose($this->Server);
            $this->Server = null;
        }

        return $this;
    }

    public function isRunning(): bool
    {
        return !is_null($this->Server);
    }

    /**
     * Wait for a request and return a response
     *
     * @template T
     * @param callable $callback Receives an {@see HttpRequest} and returns an
     * {@see HttpResponse}. May also set `$continue = true` to make
     * {@see HttpServer::listen()} wait for another request, or use `$return =
     * <value>` to pass `<value>` back to the caller.
     * ```php
     * fn(HttpRequest $request, bool &$continue, &$return): HttpResponse
     * ```
     * @phpstan-param callable(HttpRequest, bool &$continue, T &$return): HttpResponse $callback
     * @return T|null
     */
    public function listen(callable $callback, ?int $timeout = null)
    {
        if (!$this->Server) {
            throw new RuntimeException('start() must be called first');
        }

        $timeout = is_null($timeout) ? $this->Timeout : $timeout;
        do {
            $peer   = null;
            $socket = stream_socket_accept($this->Server, $timeout, $peer);
            $client = $peer ? preg_replace('/:[0-9]+$/', '', $peer) : null;
            $peer   = $peer ?: '<unknown>';

            if (!$socket) {
                throw new RuntimeException("Unable to accept connection from $peer");
            }

            $startLine = null;
            $version   = null;
            $headers   = new CurlerHeaders();
            $body      = null;
            do {
                if (($line = fgets($socket)) === false) {
                    throw new RuntimeException("Error reading request from $peer");
                }

                if (is_null($startLine)) {
                    $startLine = explode(' ', rtrim($line, "\r\n"));
                    if (count($startLine) != 3 ||
                            !in_array($startLine[0], HttpRequestMethods::ALL, true) ||
                            !preg_match(
                                '/^HTTP\/([0-9]+(?:\.[0-9]+)?)$/',
                                $startLine[2],
                                $version
                            )) {
                        throw new RuntimeException("Invalid HTTP request from $peer");
                    }
                    continue;
                }

                $headers = $headers->addRawHeader($line);

                if (!trim($line)) {
                    break;
                }
            } while (true);

            /** @todo Add support for Transfer-Encoding */
            if ($length = $headers->getHeaderValue('Content-Length', CurlerHeadersFlag::DISCARD_REPEATED)) {
                if (($body = fread($socket, (int) $length)) === false) {
                    throw new RuntimeException("Error reading request body from $peer");
                }
            }

            [[$method, $target], [1 => $version]] = [$startLine, $version];
            $request =
                new HttpRequest($method, $target, $version, $headers, $body, $client);

            Console::debug("$method request received from $client:", $target);

            $continue = false;
            $return   = null;

            try {
                /** @var HttpResponse */
                $response = $callback($request, $continue, $return);
            } finally {
                fwrite($socket, (string) ($response ?? new HttpResponse(
                    'Internal server error', 500, 'Internal Server Error'
                )));
                fclose($socket);
            }
        } while ($continue);

        return $return;
    }
}
