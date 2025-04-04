<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HasHttpHeader as Header;
use Salient\Contract\Http\HasRequestMethod as Method;
use Salient\Http\HttpResponse;
use Salient\Http\HttpUtil;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use RuntimeException;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

// Usage: php http-server.php [<host>[:<port>] [<timeout>]] [-- [<filename>...]]

/** @var string[] */
$_args = $_SERVER['argv'];
$key = array_search('--', $_args, true);
/** @var int|false $key */
$args = $key === false
    ? array_slice($_args, 1)
    : array_slice($_args, 1, $key - 1);
$host = $args[0] ?? 'localhost:3007';
$timeout = (int) ($args[1] ?? -1);

if ($key === false) {
    $responses[] = Str::setEol(File::getContents(\STDIN), "\r\n");
} else {
    $responses = null;
    $count = count($_args);
    for ($i = $key + 1; $i < $count; $i++) {
        $responses[] = Str::setEol(File::getContents($_args[$i]), "\r\n");
    }
}

$host = explode(':', $host, 2);
$port = (int) ($host[1] ?? 3007);
$host = $host[0];
$socket = "tcp://$host:$port";

$errorCode = null;
$errorMessage = null;
$server = @stream_socket_server($socket, $errorCode, $errorMessage);
if ($server === false) {
    throw new RuntimeException(sprintf(
        'Error starting server at %s (%d: %s)',
        $socket,
        $errorCode,
        $errorMessage,
    ));
}

fprintf(\STDERR, '==> Server started at http://%s:%d' . \PHP_EOL, $host, $port);

$i = 0;
do {
    fprintf(\STDERR, ' -> Waiting for client' . \PHP_EOL);
    $stream = @stream_socket_accept($server, $timeout, $peer);
    if ($stream === false) {
        $error = error_get_last();
        throw new RuntimeException($error['message'] ?? sprintf(
            'Error accepting connection at %s',
            $socket,
        ));
    }

    if ($peer === null) {
        throw new RuntimeException('No client address');
    }

    Regex::match('/(?<addr>.*?)(?::(?<port>[0-9]+))?$/', $peer, $matches);

    /** @var array{addr:string,port?:string} $matches */
    $remoteHost = $matches['addr'];
    $remotePort = $matches['port'] ?? null;

    fprintf(
        \STDERR,
        ' -> Accepted connection from %s:%s' . \PHP_EOL,
        $remoteHost,
        $remotePort
    );

    TestUtil::dumpHttpMessage($stream, true, $startLine);

    if ($startLine === null) {
        throw new RuntimeException('Invalid or empty request');
    }

    $response = $responses[$i]
        ?? (string) (new HttpResponse())
            ->withHeader(Header::HEADER_DATE, HttpUtil::getDate())
            ->withHeader(Header::HEADER_SERVER, HttpUtil::getProduct());

    [$method] = explode(' ', $startLine, 2);
    if ($method === Method::METHOD_HEAD) {
        $response = explode("\r\n\r\n", $response, 2);
        if (isset($response[1])) {
            $response[1] = '';
        }
        $response = implode("\r\n\r\n", $response);
    }

    fprintf(\STDERR, Inflect::format(
        strlen($response),
        ' -> Sending response #%d ({{#}} {{#:byte}})' . \PHP_EOL,
        $i++ + 1
    ));

    File::writeAll($stream, Str::setEol($response, "\r\n"));
    File::close($stream);
} while ($responses === null || $i < count($responses));

File::close($server);
