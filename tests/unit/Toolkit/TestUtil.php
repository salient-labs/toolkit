<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Contract\Http\HasHttpHeader;
use Salient\Http\Headers;
use Salient\Http\HttpUtil;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Regex;
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
        bool $addNewlines = false,
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
        $trailing = '';
        while (!@feof($stream)) {
            if ($contentLength !== null) {
                $data = File::readAll($stream, $contentLength);
                $body .= $data;
                self::dump($data, $trailing, $addNewlines);
                self::terminate($trailing, $addNewlines);
                return;
            }

            if ($chunked === true) {
                $line = File::readLine($stream);
                self::dump($line, $trailing, $addNewlines);
                if ($bodyReceived) {
                    if (rtrim($line, "\r\n") === '') {
                        self::terminate($trailing, $addNewlines);
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
                $data = File::readAll($stream, $chunkSize);
                $body .= $data;
                self::dump($data, $trailing, $addNewlines);
                $line = File::readLine($stream);
                self::dump($line, $trailing, $addNewlines);
                $line = rtrim($line, "\r\n");
                if ($line !== '') {
                    throw new RuntimeException('Invalid chunk');
                }
                continue;
            }

            if ($chunked === false) {
                $data = File::getContents($stream);
                $body .= $data;
                self::dump($data, $trailing, $addNewlines);
                continue;
            }

            $line = File::readLine($stream);
            self::dump($line, $trailing, $addNewlines);
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
                    self::terminate($trailing, $addNewlines);
                    return;
                }
                $chunked = false;
            }
        }

        self::terminate($trailing, $addNewlines);
    }

    private static function dump(string $data, string &$trailing, bool $addNewlines): void
    {
        echo $data;

        if ($addNewlines) {
            $trimmed = rtrim($data);
            if ($trimmed === '') {
                $trailing .= $data;
            } elseif ($trimmed !== $data) {
                $trailing = (string) substr($data, strlen($trimmed));
            } else {
                $trailing = '';
            }
        }
    }

    private static function terminate(string $trailing, bool $addNewlines): void
    {
        if ($addNewlines) {
            $count = Regex::matchAll('/(?:\r\n|\n|\r)/', $trailing);
            if ($count < 2) {
                echo str_repeat("\r\n", 2 - $count);
            }
        }
    }
}
