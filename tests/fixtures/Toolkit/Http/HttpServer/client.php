<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Core\Char;
use Salient\Contract\Http\HttpHeader;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Str;
use Salient\Http\HttpHeaders;

require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Usage: php client.php [<host>[:<port>] [<method> [<target> [<timeout> ["<header>: <value>"...]]]]]

$host = $_SERVER['argv'][1] ?? 'localhost:3008';
$method = $_SERVER['argv'][2] ?? 'GET';
$target = $_SERVER['argv'][3] ?? '/';
$timeout = (int) ($_SERVER['argv'][4] ?? -1);
$headers = array_slice($_SERVER['argv'], 5);

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
    $body !== '' ||
    (['POST' => true, 'PUT' => true, 'PATCH' => true][$method] ?? false)
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

$headers = new HttpHeaders();
$contentLength = null;
$chunked = null;
$bodyReceived = false;
while (!@feof($client)) {
    if ($contentLength !== null) {
        echo File::readAll($client, $contentLength);
        break;
    }

    if ($chunked === true) {
        echo $line = File::readLine($client);
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
        /** @var int<0,max> */
        $chunkSize = (int) hexdec($line);
        if ($chunkSize === 0) {
            $bodyReceived = true;
            continue;
        }
        echo File::readAll($client, $chunkSize);
        echo $line = File::readLine($client);
        $line = rtrim($line, "\r\n");
        if ($line !== '') {
            throw new RuntimeException('Invalid chunk');
        }
        continue;
    }

    if ($chunked === false) {
        echo File::getContents($client);
        continue;
    }

    echo $line = File::readLine($client);
    $headers = $headers->addLine($line);
    if ($headers->hasLastLine()) {
        if ($headers->hasHeader(HttpHeader::TRANSFER_ENCODING)) {
            $encoding = Arr::last(Str::split(',', $headers->getHeaderLine(HttpHeader::TRANSFER_ENCODING)));
            $chunked = $encoding === 'chunked';
            continue;
        }
        $contentLength = $headers->getContentLength();
        if ($contentLength !== null) {
            continue;
        }
        break;
    }
}

File::close($client);
