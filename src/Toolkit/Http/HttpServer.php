<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\ResponseInterface;
use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\Exception\InvalidHeaderException;
use Salient\Contract\Http\Message\HttpResponseInterface;
use Salient\Contract\Http\Message\HttpServerRequestInterface;
use Salient\Contract\Http\HasHeader;
use Salient\Contract\Http\HasRequestMethod;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Core\Facade\Console;
use Salient\Http\Exception\HttpServerException;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\File;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;

/**
 * A simple HTTP server
 */
class HttpServer implements Immutable, HasHeader, HasRequestMethod
{
    use ImmutableTrait;

    protected string $Host;
    protected int $Port;
    protected int $Timeout;
    private string $ProxyHost;
    private int $ProxyPort;
    private bool $ProxyTls;
    private string $ProxyBasePath;
    private string $Socket;
    /** @var resource|null */
    private $Server;

    /**
     * @param int $timeout The default number of seconds to wait for a request
     * before timing out. Use a negative value to wait indefinitely.
     */
    public function __construct(string $host, int $port, int $timeout = -1)
    {
        $this->Host = $host;
        $this->Port = $port;
        $this->Timeout = $timeout;
    }

    /**
     * @internal
     */
    public function __clone()
    {
        unset($this->Socket);
        $this->Server = null;
    }

    /**
     * Get the server's hostname or IP address
     */
    public function getHost(): string
    {
        return $this->Host;
    }

    /**
     * Get the server's TCP port
     */
    public function getPort(): int
    {
        return $this->Port;
    }

    /**
     * Get the default number of seconds to wait for a request before timing out
     */
    public function getTimeout(): int
    {
        return $this->Timeout;
    }

    /**
     * Check if the server is configured to run behind a proxy server
     */
    public function hasProxy(): bool
    {
        return isset($this->ProxyHost);
    }

    /**
     * Get the hostname or IP address of the proxy server
     */
    public function getProxyHost(): string
    {
        return $this->ProxyHost;
    }

    /**
     * Get the TCP port of the proxy server
     */
    public function getProxyPort(): int
    {
        return $this->ProxyPort;
    }

    /**
     * Check if connections to the proxy server are encrypted
     */
    public function getProxyTls(): bool
    {
        return $this->ProxyTls;
    }

    /**
     * Get the base path at which the server can be reached via the proxy server
     */
    public function getProxyBasePath(): string
    {
        return $this->ProxyBasePath;
    }

    /**
     * Get an instance configured to run behind a proxy server
     *
     * Returns a server that listens at the same host and port, but refers to
     * itself in client-facing URIs as:
     *
     * ```
     * http[s]://<proxy_host>[:<proxy_port>][<proxy_base_path>]
     * ```
     *
     * @return static
     */
    public function withProxy(
        string $host,
        int $port,
        ?bool $tls = null,
        string $basePath = ''
    ): self {
        $basePath = trim($basePath, '/');
        if ($basePath !== '') {
            $basePath = '/' . $basePath;
        }

        return $this
            ->with('ProxyHost', $host)
            ->with('ProxyPort', $port)
            ->with('ProxyTls', $tls ?? ($port === 443))
            ->with('ProxyBasePath', $basePath);
    }

    /**
     * Get an instance that is not configured to run behind a proxy server
     *
     * @return static
     */
    public function withoutProxy(): self
    {
        if (!isset($this->ProxyHost)) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->ProxyHost);
        unset($clone->ProxyPort);
        unset($clone->ProxyTls);
        unset($clone->ProxyBasePath);
        return $clone;
    }

    /**
     * Get the server's client-facing base URI with no trailing slash
     */
    public function getBaseUri(): string
    {
        if (!isset($this->ProxyHost)) {
            return $this->Port === 80
                ? sprintf('http://%s', $this->Host)
                : sprintf('http://%s:%d', $this->Host, $this->Port);
        }

        return ($this->ProxyTls && $this->ProxyPort === 443)
            || (!$this->ProxyTls && $this->ProxyPort === 80)
                ? sprintf(
                    '%s://%s%s',
                    $this->ProxyTls ? 'https' : 'http',
                    $this->ProxyHost,
                    $this->ProxyBasePath,
                )
                : sprintf(
                    '%s://%s:%d%s',
                    $this->ProxyTls ? 'https' : 'http',
                    $this->ProxyHost,
                    $this->ProxyPort,
                    $this->ProxyBasePath,
                );
    }

    /**
     * Get the server's client-facing URI scheme
     */
    public function getScheme(): string
    {
        return isset($this->ProxyHost)
            ? ($this->ProxyTls ? 'https' : 'http')
            : 'http';
    }

    /**
     * Check if the server is running
     */
    public function isRunning(): bool
    {
        return $this->Server !== null;
    }

    /**
     * Start the server
     *
     * @return $this
     */
    public function start(): self
    {
        $this->assertIsNotRunning();

        $this->Socket ??= sprintf('tcp://%s:%d', $this->Host, $this->Port);

        $errorCode = null;
        $errorMessage = null;
        $server = @stream_socket_server($this->Socket, $errorCode, $errorMessage);

        if ($server === false) {
            throw new HttpServerException(sprintf(
                'Error starting server at %s (%d: %s)',
                $this->Socket,
                $errorCode,
                $errorMessage,
            ));
        }

        $this->Server = $server;

        return $this;
    }

    /**
     * Stop the server
     *
     * @return $this
     */
    public function stop(): self
    {
        $this->assertIsRunning();

        File::close($this->Server);

        $this->Server = null;

        return $this;
    }

    /**
     * Wait for a request and return a response
     *
     * @template T
     *
     * @param callable(HttpServerRequestInterface $request, bool &$continue, T|null &$return): ResponseInterface $callback Receives
     * an {@see HttpServerRequestInterface} and returns a
     * {@see ResponseInterface}. May also set `$continue = true` to make
     * {@see HttpServer::listen()} wait for another request, or pass a value
     * back to the caller by assigning it to `$return`.
     * @return T|null
     */
    public function listen(callable $callback, ?int $timeout = null)
    {
        $this->assertIsRunning();

        $timeout ??= $this->Timeout;
        do {
            $socket = @stream_socket_accept($this->Server, $timeout, $peer);
            $this->maybeThrow(
                $socket,
                'Error accepting connection at %s',
                $this->Socket,
            );

            if ($peer === null) {
                throw new HttpServerException('No client address');
            }

            Regex::match('/(?<addr>.*?)(?::(?<port>[0-9]+))?$/', $peer, $matches);

            /** @var array{addr:string,port?:string} $matches */
            $peer = $matches['addr'];
            $serverParams = [
                'REMOTE_ADDR' => $matches['addr'],
                'REMOTE_PORT' => $matches['port'] ?? '',
            ];

            $method = null;
            $target = '';
            $targetUri = null;
            $version = '';
            $headers = new HttpHeaders();
            $body = null;
            do {
                $line = @fgets($socket);
                if ($line === false) {
                    try {
                        File::checkEof($socket);
                    } catch (FilesystemErrorException $ex) {
                        // @codeCoverageIgnoreStart
                        throw new HttpServerException(sprintf(
                            'Error reading request from %s',
                            $peer,
                        ), $ex);
                        // @codeCoverageIgnoreEnd
                    }
                    throw new HttpServerException(sprintf(
                        'Incomplete request from %s',
                        $peer,
                    ));
                }

                if ($method === null) {
                    if (substr($line, -2) !== "\r\n") {
                        // @codeCoverageIgnoreStart
                        throw new HttpServerException(sprintf(
                            'Request line from %s does not end with CRLF',
                            $peer,
                        ));
                        // @codeCoverageIgnoreEnd
                    }

                    $startLine = explode(' ', substr($line, 0, -2));

                    if (
                        count($startLine) !== 3
                        || !HttpUtil::isRequestMethod($startLine[0])
                        || !Regex::match('/^HTTP\/([0-9](?:\.[0-9])?)$/D', $startLine[2], $matches)
                    ) {
                        throw new HttpServerException(sprintf(
                            'Invalid request line from %s: %s',
                            $peer,
                            $line,
                        ));
                    }

                    $method = $startLine[0];
                    $target = $startLine[1];
                    $version = $matches[1];

                    if ($target === '*') {
                        if ($method !== self::METHOD_OPTIONS) {
                            throw new HttpServerException(sprintf(
                                'Invalid request from %s for target %s: %s',
                                $peer,
                                $target,
                                $method,
                            ));
                        }
                        continue;
                    }

                    if ($method === self::METHOD_CONNECT) {
                        if (!Uri::isAuthorityForm($target)) {
                            throw new HttpServerException(sprintf(
                                'Invalid request target for %s from %s: %s',
                                $method,
                                $peer,
                                $target,
                            ));
                        }
                        $targetUri = new Uri('//' . $target, true);
                        continue;
                    }

                    try {
                        $targetUri = new Uri($target, true);
                    } catch (InvalidArgumentException $ex) {
                        throw new HttpServerException(sprintf(
                            'Invalid request target for %s from %s: %s',
                            $method,
                            $peer,
                            $target,
                        ), $ex);
                    }
                    continue;
                }

                $headers = $headers->addLine($line, true);
                if ($headers->hasLastLine()) {
                    break;
                }
            } while (true);

            // As per [RFC7230], Section 5.5 ("Effective Request URI")
            $uri = implode('', [
                $this->getScheme(),
                '://',
                Str::coalesce($headers->getOnlyHeaderValue(self::HEADER_HOST), $this->ProxyHost ?? $this->Host),
            ]);
            if (!Regex::match('/:[0-9]++$/', $uri)) {
                $uri .= ':' . ($this->ProxyPort ?? $this->Port);
            }
            try {
                $uri = new Uri($uri, true);
            } catch (InvalidArgumentException $ex) {
                throw new HttpServerException(sprintf(
                    'Invalid request URI from %s: %s',
                    $peer,
                    $uri,
                ), $ex);
            }
            if ($targetUri !== null) {
                $uri = $uri->follow($targetUri);
            }

            /** @todo Handle requests without Content-Length */
            /** @todo Add support for Transfer-Encoding */
            try {
                $length = $headers->getContentLength();
            } catch (InvalidHeaderException $ex) {
                throw new HttpServerException(sprintf(
                    'Invalid %s in request from %s',
                    self::HEADER_CONTENT_LENGTH,
                    $peer,
                ), $ex);
            }
            if ($length === 0) {
                $body = '';
            } elseif ($length !== null) {
                $body = @fread($socket, $length);
                if ($body === false) {
                    throw new HttpServerException(sprintf(
                        'Error reading request body from %s',
                        $peer,
                    ));
                }
                if (strlen($body) < $length) {
                    throw new HttpServerException(sprintf(
                        'Incomplete request body from %s',
                        $peer,
                    ));
                }
            }

            $request = new HttpServerRequest(
                $method,
                $uri,
                $serverParams,
                $body,
                $headers,
                $target,
                $version,
            );

            Console::debug(sprintf('%s %s received from %s', $method, (string) $uri, $peer));

            $continue = false;
            $return = null;
            $response = null;

            try {
                $response = $callback($request, $continue, $return);
            } finally {
                $response = $response instanceof ResponseInterface
                    ? ($response instanceof HttpResponseInterface
                        ? $response
                        : HttpResponse::fromPsr7($response))
                    : new HttpResponse(500, 'Internal server error');
                File::write($socket, (string) $response);
                File::close($socket);
            }
        } while ($continue);

        return $return;
    }

    /**
     * @phpstan-assert null $this->Server
     */
    private function assertIsNotRunning(): void
    {
        if ($this->Server !== null) {
            throw new HttpServerException('Server is running');
        }
    }

    /**
     * @phpstan-assert !null $this->Server
     */
    private function assertIsRunning(): void
    {
        if ($this->Server === null) {
            throw new HttpServerException('Server is not running');
        }
    }

    /**
     * @template T
     *
     * @param T $result
     * @param string|int|float ...$args
     * @return (T is false ? never : T)
     * @phpstan-param T|false $result
     * @phpstan-return ($result is false ? never : T)
     */
    private function maybeThrow($result, string $message, ...$args)
    {
        if ($result === false) {
            $error = error_get_last();
            if ($error) {
                throw new HttpServerException($error['message']);
            }
            throw new HttpServerException(sprintf($message, ...$args));
        }
        return $result;
    }
}
