<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HasRequestMethod as Method;
use Salient\Utility\File;
use Salient\Utility\Str;
use RuntimeException;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

// Usage: php http-client.php [<host>[:<port>] [<method> [<target> [<timeout> ["<header>: <value>"...]]]]]

/** @var string[] */
$args = $_SERVER['argv'];
$host = $args[1] ?? 'localhost:3008';
$method = $args[2] ?? 'GET';
$target = $args[3] ?? '/';
$timeout = (int) ($args[4] ?? -1);
$headers = array_slice($args, 5);

$body = $method === 'GET' || $method === 'HEAD'
    ? ''
    : File::getContents(\STDIN);

$host = explode(':', $host, 2);
$port = (int) ($host[1] ?? 80);
$host = $host[0];
$socket = "tcp://$host:$port";

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

$headers = implode(\PHP_EOL, $headers);
$request = <<<EOF
$headers

$body
EOF;

$errorCode = null;
$errorMessage = null;
$client = @stream_socket_client($socket, $errorCode, $errorMessage, $timeout);
if ($client === false) {
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
> %s
>

EOF,
    $host,
    $port,
    str_replace(\PHP_EOL, \PHP_EOL . '> ', $headers),
);

File::writeAll($client, Str::setEol($request, "\r\n"));

TestUtil::dumpHttpMessage($client);

File::close($client);
