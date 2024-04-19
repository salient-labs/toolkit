<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HttpHeader;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Http;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Http\HttpResponse;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

// Usage: php http-server.php [<host>[:<port>] [<timeout>]] [-- [<filename>...]]

$key = array_search('--', $_SERVER['argv'], true);
/** @var int|false $key */
$args = $key === false
    ? array_slice($_SERVER['argv'], 1)
    : array_slice($_SERVER['argv'], 1, $key - 1);
$host = $args[0] ?? 'localhost:3007';
$timeout = (int) ($args[1] ?? -1);

if ($key === false) {
    $responses[] = Str::setEol(File::getContents(\STDIN), "\r\n");
} else {
    $responses = null;
    $count = count($_SERVER['argv']);
    for ($i = $key + 1; $i < $count; $i++) {
        $responses[] = Str::setEol(File::getContents($_SERVER['argv'][$i]), "\r\n");
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

    Pcre::match('/(?<addr>.*?)(?::(?<port>[0-9]+))?$/', $peer, $matches);

    /** @var array{addr:string,port?:string} $matches */
    $remoteHost = $matches['addr'];
    $remotePort = $matches['port'] ?? null;

    fprintf(
        \STDERR,
        ' -> Accepted connection from %s:%s' . \PHP_EOL,
        $remoteHost,
        $remotePort
    );

    TestUtility::dumpHttpMessage($stream);

    $response = $responses[$i]
        ?? (string) (new HttpResponse())
            ->withHeader(HttpHeader::DATE, Http::getDate())
            ->withHeader(HttpHeader::SERVER, Http::getProduct());

    fprintf(\STDERR, Inflect::format(
        strlen($response),
        ' -> Sending response #%d ({{#}} {{#:byte}})' . \PHP_EOL,
        $i++ + 1
    ));

    File::writeAll($stream, Str::setEol($response, "\r\n"));
    File::close($stream);
} while ($responses === null || $i < count($responses));

File::close($server);
