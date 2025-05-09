<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HasHttpHeader as Header;
use Salient\Contract\Http\HasRequestMethod as Method;
use Salient\Http\Message\Response;
use Salient\Http\HttpUtil;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use RuntimeException;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/*
 * Usage: php http-server.php [--add-newlines] [<host>[:<port>] [<timeout>]] [-- [<filename>...]]
 *
 * Listens for HTTP requests and returns prepared responses in the given order
 * before exiting.
 *
 * If no responses are given, one is read from the standard input before the
 * server starts.
 *
 * If `--` is given with no filename, the server returns empty "HTTP/1.1 200 OK"
 * responses until it is interrupted.
 *
 * Use `--add-newlines` to add one or two newlines after requests with one or
 * zero trailing newlines respectively.
 *
 * Defaults:
 * - <host>: localhost
 * - <port>: 3007
 * - <timeout>: -1 (wait indefinitely)
 */

/** @var string[] */
$_args = $_SERVER['argv'];
/** @var int|false */
$key = array_search('--', $_args, true);
$args = $key === false
    ? array_slice($_args, 1)
    : array_slice($_args, 1, $key - 1);

$addNewlines = false;
if ($args && $args[0] === '--add-newlines') {
    array_shift($args);
    $addNewlines = true;
}

$host = $args[0] ?? 'localhost:3007';
$timeout = (int) ($args[1] ?? -1);

if ($key === false) {
    $responses[] = File::getContents(\STDIN);
} else {
    $responses = [];
    $count = count($_args);
    for ($i = $key + 1; $i < $count; $i++) {
        $responses[] = File::getContents($_args[$i]);
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

$i = -1;
do {
    $i++;
    fprintf(\STDERR, ' -> Waiting for client' . \PHP_EOL);
    $stream = @stream_socket_accept($server, $timeout, $client);
    if ($stream === false) {
        $error = error_get_last();
        throw new RuntimeException($error['message'] ?? sprintf(
            'Error accepting connection at %s',
            $socket,
        ));
    }

    if ($client === null) {
        throw new RuntimeException('No client address');
    }

    Regex::match('/(?<addr>.*?)(?::(?<port>[0-9]++))?$/D', $client, $matches);

    /** @var array{addr:string,port?:string} $matches */
    $remoteHost = $matches['addr'];
    $remotePort = $matches['port'] ?? null;

    fprintf(
        \STDERR,
        ' -> Accepted connection from %s:%s' . \PHP_EOL,
        $remoteHost,
        $remotePort,
    );

    $isLastResponse = $responses && $i === count($responses) - 1;
    TestUtil::dumpHttpMessage($stream, $addNewlines && !$isLastResponse, true, $startLine);

    if ($startLine === null) {
        throw new RuntimeException('Invalid or empty request');
    }

    $response = $responses[$i]
        ?? (string) (new Response())
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
        $i + 1,
    ));

    File::writeAll($stream, $response);
    File::close($stream);
} while (!$isLastResponse);

File::close($server);
