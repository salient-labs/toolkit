<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HasRequestMethod as Method;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Test;
use RuntimeException;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

/*
 * Usage: php http-client.php [--add-newlines] [<host>[:<port>] [<timeout>] [<method> [<target> ["<header>: <value>"...]]]] [-- [<method> [<target> ["<header>: <value>"...]]]]...
 *
 * Sends one or more requests to an HTTP server.
 *
 * If a method other than `GET` or `HEAD` is given, a message body is read from
 * the standard input and reused for subsequent requests as needed.
 *
 * Use `--add-newlines` to add one or two newlines after responses with one or
 * zero trailing newlines respectively.
 *
 * Defaults:
 * - <host>: localhost
 * - <port>: 80, or 3008 if no host is given
 * - <timeout>: -1 (wait indefinitely)
 * - <method>: GET
 * - <target>: /
 */

/** @var string[] */
$_args = $_SERVER['argv'];
/** @var int|false */
$key = array_search('--', $_args, true);
[$args, $_args] = $key === false
    ? [array_slice($_args, 1), []]
    : [array_slice($_args, 1, $key - 1), array_slice($_args, $key + 1)];

$addNewlines = false;
if ($args && $args[0] === '--add-newlines') {
    array_shift($args);
    $addNewlines = true;
}

$host = array_shift($args) ?? 'localhost:3008';
$timeout = $args && Test::isInteger($args[0])
    ? (int) array_shift($args)
    : -1;

$host = explode(':', $host, 2);
$port = (int) ($host[1] ?? 80);
$host = $host[0];
$socket = "tcp://$host:$port";

$i = -1;
do {
    $i++;
    $method = $args[0] ?? 'GET';
    $target = $args[1] ?? '/';
    $headers = array_slice($args, 2);

    $body = $method === 'GET' || $method === 'HEAD'
        ? ''
        : ($stdin ??= File::getContents(\STDIN));

    array_unshift(
        $headers,
        sprintf('%s %s HTTP/1.1', $method, $target),
        sprintf('Host: %s', $port === 80 ? $host : "$host:$port"),
        'Accept: */*',
    );

    if (
        $body !== ''
        || ([
            Method::METHOD_POST => true,
            Method::METHOD_PUT => true,
            Method::METHOD_PATCH => true,
            Method::METHOD_DELETE => true,
        ][$method] ?? false)
    ) {
        $headers[] = 'Content-Length: ' . strlen($body);
    }

    $request = implode("\r\n", $headers) . "\r\n\r\n" . $body;

    $errorCode = null;
    $errorMessage = null;
    $stream = @stream_socket_client($socket, $errorCode, $errorMessage, $timeout);
    if ($stream === false) {
        throw new RuntimeException(sprintf(
            'Error connecting to %s (%d: %s)',
            $socket,
            $errorCode,
            $errorMessage,
        ));
    }

    fprintf(
        \STDERR,
        <<<EOF
==> Connected to %s:%d
 -> Sending request #%d (%s)
> %s
>

EOF,
        $host,
        $port,
        $i + 1,
        Inflect::format(strlen($request), '{{#}} {{#:byte}}'),
        implode(\PHP_EOL . '> ', $headers),
    );

    File::writeAll($stream, $request);

    $isLastRequest = !$_args;
    TestUtil::dumpHttpMessage($stream, $addNewlines && !$isLastRequest);

    File::close($stream);

    if ($isLastRequest) {
        break;
    }

    /** @var int|false */
    $key = array_search('--', $_args, true);
    [$args, $_args] = $key === false
        ? [$_args, []]
        : [array_slice($_args, 0, $key), array_slice($_args, $key + 1)];
} while (true);
