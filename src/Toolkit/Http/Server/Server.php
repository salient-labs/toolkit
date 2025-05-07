<?php declare(strict_types=1);

namespace Salient\Http\Server;

use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\Exception\InvalidHeaderException as InvalidHeaderExceptionInterface;
use Salient\Contract\Http\Message\ServerRequestInterface;
use Salient\Contract\Http\HasHttpHeader;
use Salient\Contract\Http\HasRequestMethod;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Http\Exception\HttpServerException;
use Salient\Http\Exception\InvalidHeaderException;
use Salient\Http\Message\Response;
use Salient\Http\Message\ServerRequest;
use Salient\Http\Headers;
use Salient\Http\HttpUtil;
use Salient\Http\Uri;
use Salient\Utility\File;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * A simple in-process HTTP/1.1 server
 *
 * @todo Add support for chunked transfers
 *
 * @api
 */
class Server implements Immutable, HasHttpHeader, HasRequestMethod
{
    use ImmutableTrait;

    private string $Host;
    private int $Port;
    private int $Timeout;
    private string $ProxyHost;
    private int $ProxyPort;
    private bool $ProxyHasTls;
    private string $ProxyPath;

    // --

    private string $Address;
    /** @var resource|null */
    private $Server = null;
    private string $LocalIpAddress;
    private int $LocalPort;

    /**
     * @api
     *
     * @param int $timeout The number of seconds to wait for a request, or an
     * integer less than `0` to wait indefinitely. May be overridden via
     * {@see listen()}.
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
    protected function __clone()
    {
        unset($this->Address);
        $this->Server = null;
        unset($this->LocalIpAddress);
        unset($this->LocalPort);
    }

    /**
     * Get the hostname or IP address of the server
     */
    public function getHost(): string
    {
        return $this->Host;
    }

    /**
     * Get the TCP port of the server
     */
    public function getPort(): int
    {
        return $this->Port;
    }

    /**
     * Get the number of seconds the server will wait for a request
     *
     * If an integer less than `0` is returned, the server will wait
     * indefinitely.
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
     *
     * @throws LogicException if the server is not configured to run behind a
     * proxy server.
     */
    public function getProxyHost(): string
    {
        $this->assertHasProxy();
        return $this->ProxyHost;
    }

    /**
     * Get the TCP port of the proxy server
     *
     * @throws LogicException if the server is not configured to run behind a
     * proxy server.
     */
    public function getProxyPort(): int
    {
        $this->assertHasProxy();
        return $this->ProxyPort;
    }

    /**
     * Check if the proxy server uses TLS
     *
     * @throws LogicException if the server is not configured to run behind a
     * proxy server.
     */
    public function proxyHasTls(): bool
    {
        $this->assertHasProxy();
        return $this->ProxyHasTls;
    }

    /**
     * Get the path at which the server can be reached via the proxy server
     *
     * @throws LogicException if the server is not configured to run behind a
     * proxy server.
     */
    public function getProxyPath(): string
    {
        $this->assertHasProxy();
        return $this->ProxyPath;
    }

    /**
     * Get an instance configured to run behind a proxy server
     *
     * Returns a server that listens at the same host and port, but refers to
     * itself in client-facing URIs as:
     *
     * ```
     * http[s]://<proxy_host>[:<proxy_port>][<proxy_path>]
     * ```
     *
     * @return static
     */
    public function withProxy(
        string $host,
        int $port,
        ?bool $hasTls = null,
        string $path = ''
    ): self {
        $hasTls ??= $port === 443;
        $path = trim($path, '/');
        if ($path !== '') {
            $path = '/' . $path;
        }

        return $this
            ->with('ProxyHost', $host)
            ->with('ProxyPort', $port)
            ->with('ProxyHasTls', $hasTls)
            ->with('ProxyPath', $path);
    }

    /**
     * Get an instance configured to run without a proxy server
     *
     * @return static
     */
    public function withoutProxy(): self
    {
        return $this
            ->without('ProxyHost')
            ->without('ProxyPort')
            ->without('ProxyHasTls')
            ->without('ProxyPath');
    }

    /**
     * Get the client-facing URI of the server, with no trailing slash
     *
     * Call this method after {@see start()} if using dynamic port allocation.
     */
    public function getUri(): Uri
    {
        return isset($this->ProxyHost)
            ? (new Uri())
                ->withScheme($this->ProxyHasTls ? 'https' : 'http')
                ->withHost($this->ProxyHost)
                ->withPort($this->ProxyPort)
                ->withPath($this->ProxyPath)
            : (new Uri())
                ->withScheme('http')
                ->withHost($this->Host)
                ->withPort($this->LocalPort ?? $this->Port);
    }

    /**
     * Check if the server is running
     */
    public function isRunning(): bool
    {
        return (bool) $this->Server;
    }

    /**
     * Get the IP address to which the server is bound
     *
     * @throws LogicException if the server is not running.
     */
    public function getLocalIpAddress(): string
    {
        $this->assertIsRunning();
        return $this->LocalIpAddress;
    }

    /**
     * Get the TCP port on which the server is listening
     *
     * @throws LogicException if the server is not running.
     */
    public function getLocalPort(): int
    {
        $this->assertIsRunning();
        return $this->LocalPort;
    }

    /**
     * Start the server
     *
     * @return $this
     * @throws LogicException if the server is already running.
     */
    public function start(): self
    {
        $this->assertIsNotRunning();

        $address = $this->Address ??= sprintf(
            'tcp://%s:%d',
            $this->Host,
            $this->Port,
        );

        $errorCode = null;
        $errorMessage = null;
        $server = @stream_socket_server($address, $errorCode, $errorMessage);

        if ($server === false) {
            throw new HttpServerException(sprintf(
                'Error starting server at %s (%d: %s)',
                $address,
                $errorCode,
                $errorMessage,
            ));
        }

        $address = @stream_socket_get_name($server, false);

        if ($address === false || ($pos = strrpos($address, ':')) === false) {
            throw new HttpServerException('Error getting server address');
        }

        $this->Server = $server;
        $this->LocalIpAddress = substr($address, 0, $pos);
        $this->LocalPort = (int) substr($address, $pos + 1);

        return $this;
    }

    /**
     * Stop the server if it is running
     *
     * @return $this
     */
    public function stop(): self
    {
        if ($this->Server) {
            File::close($this->Server, $this->Address);
            $this->Server = null;
            unset($this->LocalIpAddress);
            unset($this->LocalPort);
        }

        return $this;
    }

    /**
     * Wait for a request and provide the response returned by a listener
     *
     * If the listener returns a response with a return value, the server stops
     * listening for requests, ignoring `$limit` if given, and returns the value
     * to the caller.
     *
     * @template TReturn
     *
     * @param callable(ServerRequestInterface $request): ServerResponse<TReturn> $listener
     * @param int<-1,max> $limit If `-1` (the default), listen for requests
     * indefinitely. Otherwise, listen until `$limit` requests have been
     * received before returning `null`.
     * @param bool $catchBadRequests If `false`, throw the underlying exception
     * when an invalid request is rejected.
     * @param int|null $timeout The number of seconds to wait for a request, an
     * integer less than `0` to wait indefinitely, or `null` (the default) to
     * use the server's default timeout.
     * @param bool $strict If `true`, strict \[RFC9112] compliance is enforced.
     * @return TReturn|null
     * @throws LogicException if the server is not running.
     */
    public function listen(
        callable $listener,
        int $limit = -1,
        bool $catchBadRequests = true,
        ?int $timeout = null,
        bool $strict = false
    ) {
        $this->assertIsRunning();

        while ($limit) {
            $stream = null;
            $response = null;
            try {
                $request = $this->getRequest($stream, $timeout, $strict);
                $response = $listener($request);
                if ($response->hasReturnValue()) {
                    return $response->getReturnValue();
                }
            } catch (InvalidHeaderExceptionInterface $ex) {
                $response = new Response(400, $ex->getMessage());
                if (!$catchBadRequests) {
                    throw $ex;
                }
            } catch (LogicException|HttpServerException $ex) {
                throw $ex;
            } catch (Throwable $ex) {
                throw new HttpServerException($ex->getMessage(), $ex);
            } finally {
                if ($stream) {
                    if ($response) {
                        File::writeAll($stream, (string) $response);
                    }
                    File::close($stream);
                }
            }
            $limit--;
        }

        return null;
    }

    /**
     * @param resource|null $stream
     * @param-out resource $stream
     */
    private function getRequest(&$stream, ?int $timeout, bool $strict): ServerRequest
    {
        $this->assertIsRunning();

        $handle = @stream_socket_accept(
            $this->Server,
            $timeout ?? $this->Timeout,
            $client,
        );

        if ($handle === false) {
            $error = error_get_last();
            throw new HttpServerException($error['message'] ?? sprintf(
                'Error accepting connection at %s',
                $this->Address,
            ));
        }

        if ($client === null) {
            throw new HttpServerException('No client address');
        }

        Regex::match('/(?<addr>.*?)(?::(?<port>[0-9]++))?$/D', $client, $matches);

        /** @var array{addr:string,port?:string} $matches */
        $client = $matches['addr'];
        $serverParams = [
            'REMOTE_ADDR' => $matches['addr'],
            'REMOTE_PORT' => $matches['port'] ?? '',
        ];

        // Get request line
        $stream = $handle;
        $line = File::readLine($stream);

        if ($strict && substr($line, -2) !== "\r\n") {
            throw new InvalidHeaderException(
                'HTTP request line must end with CRLF',
            );
        }

        $line = $strict
            ? substr($line, 0, -2)
            : rtrim($line, "\r\n");
        $startLine = $strict
            ? explode(' ', $line, 4)
            : Regex::split('/\s++/', trim($line), 3);

        if (
            count($startLine) !== 3
            || !HttpUtil::isRequestMethod($startLine[0])
            || !Regex::match('/^HTTP\/([0-9](?:\.[0-9])?)$/D', $startLine[2], $matches)
        ) {
            throw new InvalidHeaderException(sprintf(
                'Invalid HTTP request line: %s',
                $line,
            ));
        }

        $method = $startLine[0];
        $requestTarget = $startLine[1];
        $version = $matches[1];
        $requestTargetUri = null;

        // Check request target as per [RFC9112] Section 3.2 ("Request Target")
        if ((
            ($isAsteriskForm = $requestTarget === '*')
            && $method !== self::METHOD_OPTIONS
        ) || (
            ($isAuthorityForm = HttpUtil::isAuthorityForm($requestTarget))
            && $method !== self::METHOD_CONNECT
        )) {
            throw new InvalidHeaderException(sprintf(
                "Invalid HTTP method for request target '%s': %s",
                $requestTarget,
                $method,
            ));
        } elseif (
            $method === self::METHOD_CONNECT
            && !$isAuthorityForm
        ) {
            throw new InvalidHeaderException(sprintf(
                "Invalid request target for HTTP method '%s': %s",
                $method,
                $requestTarget,
            ));
        } elseif ($isAuthorityForm) {
            $requestTargetUri = new Uri('//' . $requestTarget);
        } elseif (!$isAsteriskForm) {
            try {
                $requestTargetUri = new Uri($requestTarget, $strict);
            } catch (InvalidArgumentException $ex) {
                throw new InvalidHeaderException(sprintf(
                    'Invalid HTTP request target: %s',
                    $requestTarget,
                ), $ex);
            }
            $isAbsoluteForm = !$requestTargetUri->isRelativeReference();
            $isOriginForm = !$isAbsoluteForm
                && $requestTargetUri->getPath() !== ''
                && !array_diff_key(
                    $requestTargetUri->getComponents(),
                    ['path' => null, 'query' => null],
                );
            if (!$isAbsoluteForm && !$isOriginForm) {
                throw new InvalidHeaderException(sprintf(
                    'Invalid HTTP request target: %s',
                    $requestTarget,
                ));
            }
        }

        // Get header field lines
        $headers = new Headers();
        do {
            $line = File::readLine($stream);
            if ($line === '') {
                throw new InvalidHeaderException('Invalid HTTP field lines');
            }
            $headers = $headers->addLine($line, $strict);
        } while (!$headers->hasEmptyLine());

        // As per [RFC9112] Section 3.3 ("Reconstructing the Target URI")
        $host = $headers->getOnlyHeaderValue(self::HEADER_HOST);
        $uri = implode('', [
            $this->getUri()->getScheme(),
            '://',
            Str::coalesce($host, $this->ProxyHost ?? $this->Host),
        ]);
        if (!Regex::match('/:[0-9]++$/', $uri)) {
            $uri .= ':' . ($this->ProxyPort ?? $this->LocalPort);
        }
        try {
            $uri = new Uri($uri, $strict);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidHeaderException(sprintf(
                'Invalid HTTP request target: %s',
                $uri,
            ), $ex);
        }
        if ($requestTargetUri) {
            $uri = $uri->follow($requestTargetUri);
        }

        $length = HttpUtil::getContentLength($headers);
        if ($length === 0) {
            $body = '';
        } elseif ($length !== null) {
            $body = File::readAll($stream, $length);
        } else {
            $body = null;
        }

        return new ServerRequest(
            $method,
            $uri,
            $serverParams,
            $body,
            $headers,
            $requestTarget,
            $version,
        );
    }

    /**
     * @phpstan-assert null $this->Server
     */
    private function assertIsNotRunning(): void
    {
        if ($this->Server) {
            throw new LogicException('Server is running');
        }
    }

    /**
     * @phpstan-assert !null $this->Server
     */
    private function assertIsRunning(): void
    {
        if (!$this->Server) {
            throw new LogicException('Server is not running');
        }
    }

    private function assertHasProxy(): void
    {
        if (!isset($this->ProxyHost)) {
            throw new LogicException('Server is not configured to run behind a proxy server');
        }
    }
}
