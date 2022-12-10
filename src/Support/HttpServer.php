<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\CurlerHeadersFlag;
use Lkrms\Facade\Console;
use Lkrms\Support\HttpRequest;
use Lkrms\Support\HttpResponse;
use RuntimeException;
use Throwable;

/**
 * Listen for HTTP requests on a local address
 *
 * @property-read string $Address
 * @property-read int $Port
 * @property-read int $Timeout
 */
final class HttpServer implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Address;

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

    public function __construct(string $address, int $port, int $timeout = 300)
    {
        $this->Address = $address;
        $this->Port    = $port;
        $this->Timeout = $timeout;
    }

    public function start(): void
    {
        if ($this->Server) {
            return;
        }

        if ($server = stream_socket_server(
            "tcp://{$this->Address}:{$this->Port}",
            $errCode,
            $errMessage
        )) {
            $this->Server = $server;

            return;
        }

        throw new RuntimeException(
            "Unable to start HTTP server at {$this->Address}:{$this->Port}"
                . ($errCode ? " (error $errCode: $errMessage)" : '')
        );
    }

    public function stop(): void
    {
        if ($this->Server) {
            fclose($this->Server);
            unset($this->Server);
        }
    }

    public function isRunning(): bool
    {
        return !is_null($this->Server);
    }

    private function assertIsRunning(): void
    {
        if (!$this->Server) {
            throw new RuntimeException('start() must be called first');
        }
    }

    /**
     * Wait for a request and return a response
     *
     * @param callable $callback Handles the given {@see HttpRequest} and
     * returns an {@see HttpResponse} object. May also set `$continue = true` to
     * make {@see HttpServer::listen()} wait for another request, or use
     * `$return = <value>` to pass `<value>` back to the caller.
     * ```php
     * callback(HttpRequest $request, bool &$continue, &$return): HttpResponse
     * ```
     * @param int|null $timeout
     * @return mixed
     */
    public function listen(callable $callback, int $timeout = null)
    {
        $this->assertIsRunning();

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
            $headers   = new CurlerHeaders();
            $body      = null;
            do {
                if (($line = fgets($socket)) === false) {
                    throw new RuntimeException("Error reading request from $peer");
                }

                if (is_null($startLine)) {
                    $startLine = explode(' ', rtrim($line, "\r\n"));
                    if (count($startLine) != 3) {
                        throw new RuntimeException("Invalid HTTP request from $peer");
                    }
                    continue;
                }

                $headers->addRawHeader($line);

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

            list($method, $target, $version) = $startLine;
            $request                         = new HttpRequest($method, $target, $version, $headers, $body, $client);

            Console::debug("$method request received from $client:", $target);

            $continue = false;
            $return   = null;

            try {
                /** @var HttpResponse */
                $response = $callback($request, $continue, $return);
            } finally {
                fwrite(
                    $socket,
                    ($response ?? new HttpResponse(
                        'Internal server error', 500, 'Internal Server Error'
                    ))->getResponse()
                );
                fclose($socket);
            }
        } while ($continue);

        return $return;
    }
}
