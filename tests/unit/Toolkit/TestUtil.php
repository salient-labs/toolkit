<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HasHttpHeader;
use Salient\Http\Headers;
use Salient\Http\HttpUtil;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Str;
use RuntimeException;

final class TestUtil extends AbstractUtility implements HasHttpHeader
{
    /**
     * Read an HTTP message from a stream and write it to STDOUT
     *
     * @param resource $stream
     * @param-out Headers $headers
     * @param-out string $body
     */
    public static function dumpHttpMessage(
        $stream,
        bool $isRequest = false,
        ?string &$startLine = null,
        ?Headers &$headers = null,
        ?string &$body = null
    ): void {
        $startLine = null;
        $headers = new Headers();
        $body = '';
        $contentLength = null;
        $chunked = null;
        $bodyReceived = false;
        while (!@feof($stream)) {
            if ($contentLength !== null) {
                echo $body .= File::readAll($stream, $contentLength);
                return;
            }

            if ($chunked === true) {
                echo $line = File::readLine($stream);
                if ($bodyReceived) {
                    if (rtrim($line, "\r\n") === '') {
                        return;
                    }
                    $headers = $headers->addLine($line);
                    continue;
                }
                $line = Str::split(';', $line, 2)[0];
                if ($line === '' || strspn($line, Str::HEX) !== strlen($line)) {
                    throw new RuntimeException('Invalid chunk size');
                }
                /** @var int<0,max> */
                $chunkSize = (int) hexdec($line);
                if ($chunkSize === 0) {
                    $bodyReceived = true;
                    continue;
                }
                echo $body .= File::readAll($stream, $chunkSize);
                echo $line = File::readLine($stream);
                $line = rtrim($line, "\r\n");
                if ($line !== '') {
                    throw new RuntimeException('Invalid chunk');
                }
                continue;
            }

            if ($chunked === false) {
                echo $body .= File::getContents($stream);
                continue;
            }

            echo $line = File::readLine($stream);
            if ($startLine === null) {
                $startLine = $line;
                continue;
            }
            $headers = $headers->addLine($line);
            if ($headers->hasEmptyLine()) {
                if ($headers->hasHeader(self::HEADER_TRANSFER_ENCODING)) {
                    $encoding = Arr::last(Str::split(
                        ',',
                        $headers->getHeaderLine(self::HEADER_TRANSFER_ENCODING)
                    ));
                    $chunked = $encoding === 'chunked';
                    continue;
                }
                $contentLength = HttpUtil::getContentLength($headers);
                if ($contentLength !== null) {
                    continue;
                }
                if ($isRequest) {
                    return;
                }
                $chunked = false;
            }
        }
    }
}
