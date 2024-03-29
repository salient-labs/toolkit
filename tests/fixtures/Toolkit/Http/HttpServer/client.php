<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Core\Char;
use Salient\Contract\Http\HttpHeader;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Test;
use Salient\Http\HttpHeaders;

require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Usage: php client.php [<host>[:<port>] [<method> [<target> [<timeout> ["<header>: <value>"...]]]]]

$host = $_SERVER['argv'][1] ?? 'localhost:3008';
$method = $_SERVER['argv'][2] ?? 'GET';
$target = $_SERVER['argv'][3] ?? '/';
$timeout = (int) ($_SERVER['argv'][4] ?? -1);
$headers = array_slice($_SERVER['argv'], 5);

/**
 * @param resource $client
 */
function readBody($client, int $length): void
{
    $i = 0;
    while ($length > 0 && !@feof($client)) {
        if ($i++) {
            usleep(10000);
        }
        $body = File::read($client, $length);
        echo $body;
        $length -= strlen($body);
    }
    if ($length > 0) {
        throw new RuntimeException('Incomplete body');
    }
}

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

if ($body !== '') {
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

$request = Str::setEol($request, "\r\n");
$i = 0;
do {
    if ($i++) {
        usleep(10000);
    }
    File::maybeWrite($client, $request, $request);
} while ($request !== '');

$headers = new HttpHeaders();
$contentLength = null;
$chunked = null;
$bodyReceived = false;
while (!@feof($client)) {
    if ($contentLength !== null) {
        readBody($client, $contentLength);
        break;
    }

    if ($chunked === true) {
        $line = File::readLine($client);
        echo $line;
        if ($bodyReceived) {
            if (rtrim($line, "\r\n") === '') {
                break;
            }
            $headers = $headers->addLine($line);
            continue;
        }
        $line = Str::split(';', $line, 2)[0] ?? '';
        if ($line === '' || strspn($line, Char::HEX) !== strlen($line)) {
            throw new RuntimeException('Invalid chunk size');
        }
        $chunkSize = (int) hexdec($line);
        if ($chunkSize === 0) {
            $bodyReceived = true;
            continue;
        }
        readBody($client, $chunkSize);
        $line = File::readLine($client);
        echo $line;
        $line = rtrim($line, "\r\n");
        if ($line !== '') {
            throw new RuntimeException('Invalid chunk');
        }
        continue;
    }

    if ($chunked === false) {
        $body = File::getContents($client);
        echo $body;
        continue;
    }

    $line = File::readLine($client);
    echo $line;
    $headers = $headers->addLine($line);
    if ($headers->hasLastLine()) {
        if ($headers->hasHeader(HttpHeader::TRANSFER_ENCODING)) {
            $encoding = Arr::last(Str::split(',', $headers->getHeaderLine(HttpHeader::TRANSFER_ENCODING)));
            $chunked = $encoding === 'chunked';
            continue;
        }
        if ($headers->hasHeader(HttpHeader::CONTENT_LENGTH)) {
            $length = $headers->getOneHeaderLine(HttpHeader::CONTENT_LENGTH);
            if (!Test::isInteger($length) || (int) $length < 0) {
                throw new RuntimeException(sprintf('Invalid value in Content-Length header: %s', $contentLength));
            }
            $contentLength = (int) $length;
            continue;
        }
        break;
    }
}

File::close($client);
