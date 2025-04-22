<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\Message\MultipartStreamInterface;
use Salient\Contract\Http\HasFormDataFlag;
use Salient\Contract\Http\HasHttpHeader;
use Salient\Contract\Http\HasMediaType;
use Salient\Contract\Http\HasRequestMethod;
use Salient\Http\Exception\InvalidHeaderException;
use Salient\Http\Internal\FormData;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Date;
use Salient\Utility\Package;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Test;
use DateTimeInterface;
use DateTimeZone;
use Stringable;

/**
 * @api
 */
final class HttpUtil extends AbstractUtility implements
    HasFormDataFlag,
    HasHttpHeader,
    HasHttpRegex,
    HasMediaType,
    HasRequestMethod
{
    /**
     * @link https://www.iana.org/assignments/media-type-structured-suffix/media-type-structured-suffix.xhtml
     */
    private const SUFFIX_TYPE = [
        'gzip' => self::TYPE_GZIP,
        'json' => self::TYPE_JSON,
        'jwt' => self::TYPE_JWT,
        'xml' => self::TYPE_XML,
        'yaml' => self::TYPE_YAML,
        'zip' => self::TYPE_ZIP,
    ];

    private const ALIAS_TYPE = [
        'text/xml' => self::TYPE_XML,
    ];

    /**
     * Get the value of a Content-Length header, or null if it is not set
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|PsrMessageInterface|string $headersOrPayload
     * @return int<0,max>|null
     */
    public static function getContentLength($headersOrPayload): ?int
    {
        $headers = Headers::from($headersOrPayload);
        if (!$headers->hasHeader(self::HEADER_CONTENT_LENGTH)) {
            return null;
        }
        $length = $headers->getOnlyHeaderValue(self::HEADER_CONTENT_LENGTH, true);
        if (!Test::isInteger($length) || (int) $length < 0) {
            throw new InvalidHeaderException(sprintf(
                'Invalid value for HTTP header %s: %s',
                self::HEADER_CONTENT_LENGTH,
                $length,
            ));
        }
        return (int) $length;
    }

    /**
     * Get the value of a Content-Type header's boundary parameter, or null if
     * it is not set
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|PsrMessageInterface|string $headersOrPayload
     */
    public static function getMultipartBoundary($headersOrPayload): ?string
    {
        $headers = Headers::from($headersOrPayload);
        if (!$headers->hasHeader(self::HEADER_CONTENT_TYPE)) {
            return null;
        }
        $type = $headers->getLastHeaderValue(self::HEADER_CONTENT_TYPE);
        return self::getParameters($type, false, false)['boundary'] ?? null;
    }

    /**
     * Get preferences applied via one or more Prefer headers as per [RFC7240]
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|PsrMessageInterface|string $headersOrPayload
     * @return array<string,array{value:string,parameters:array<string,string>}>
     */
    public static function getPreferences($headersOrPayload): array
    {
        $headers = Headers::from($headersOrPayload);
        if (!$headers->hasHeader(self::HEADER_PREFER)) {
            return [];
        }
        foreach ($headers->getHeaderValues(self::HEADER_PREFER) as $pref) {
            /** @var array<string,string> */
            $params = self::getParameters($pref, true);
            if (!$params) {
                continue;
            }
            $value = reset($params);
            $name = key($params);
            unset($params[$name]);
            $prefs[$name] ??= ['value' => $value, 'parameters' => $params];
        }
        return $prefs ?? [];
    }

    /**
     * Merge preferences into a Prefer header value as per [RFC7240]
     *
     * @param array<string,array{value:string,parameters?:array<string,string>}|string> $preferences
     */
    public static function mergePreferences(array $preferences): string
    {
        foreach ($preferences as $name => $pref) {
            $lower = Str::lower($name);
            $prefs[$lower] ??= self::mergeParameters(
                is_string($pref)
                    ? [$name => $pref]
                    : [$name => $pref['value']] + ($pref['parameters'] ?? []),
            );
        }
        return implode(', ', $prefs ?? []);
    }

    /**
     * Get the value of a Retry-After header in seconds from the current time,
     * or null if it has an invalid value or is not set
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|PsrMessageInterface|string $headersOrPayload
     * @return int<0,max>|null
     */
    public static function getRetryAfter($headersOrPayload): ?int
    {
        $headers = Headers::from($headersOrPayload);
        $after = $headers->getHeaderLine(self::HEADER_RETRY_AFTER);
        if (!Test::isInteger($after)) {
            $after = strtotime($after);
            return $after === false
                ? null
                : max(0, $after - time());
        }
        return (int) $after < 0
            ? null
            : (int) $after;
    }

    /**
     * Check if a string is a recognised HTTP request method
     *
     * @phpstan-assert-if-true HttpUtil::METHOD_* $method
     */
    public static function isRequestMethod(string $method): bool
    {
        return Reflect::hasConstantWithValue(HasRequestMethod::class, $method);
    }

    /**
     * Check if a string contains only a host and port number, separated by a
     * colon
     *
     * \[RFC7230] Section 5.3.3 (authority-form): "When making a CONNECT request
     * to establish a tunnel through one or more proxies, a client MUST send
     * only the target URI's authority component (excluding any userinfo and its
     * "@" delimiter) as the request-target."
     */
    public static function isAuthorityForm(string $target): bool
    {
        return (bool) Regex::match(self::AUTHORITY_FORM_REGEX, $target);
    }

    /**
     * Check if a media type is a match for the given MIME type
     *
     * Structured syntax suffixes (e.g. `+json` in `application/vnd.api+json`)
     * are parsed as per \[RFC6838] Section 4.2.8 ("Structured Syntax Name
     * Suffixes").
     */
    public static function mediaTypeIs(string $type, string $mimeType): bool
    {
        // Extract and normalise the type and subtype
        [$type] = explode(';', $type, 2);
        $type = Str::lower(rtrim($type));
        if ((self::ALIAS_TYPE[$type] ?? $type) === $mimeType) {
            return true;
        }

        // Check for a structured syntax suffix
        $pos = strrpos($type, '+');
        if ($pos !== false) {
            $suffix = substr($type, $pos + 1);
            $type = substr($type, 0, $pos);
            return (self::ALIAS_TYPE[$type] ?? $type) === $mimeType
                || (self::SUFFIX_TYPE[$suffix] ?? null) === $mimeType;
        }
        return false;
    }

    /**
     * Get an HTTP date as per [RFC7231] Section 7.1.1.1
     */
    public static function getDate(?DateTimeInterface $date = null): string
    {
        return Date::immutable($date)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DateTimeInterface::RFC7231);
    }

    /**
     * Get semicolon-delimited parameters from the value of a header
     *
     * @return string[]
     */
    public static function getParameters(
        string $value,
        bool $firstIsParameter = false,
        bool $unquote = true,
        bool $strict = false
    ): array {
        foreach (Str::splitDelimited(';', $value, false) as $i => $param) {
            if ($i === 0 && !$firstIsParameter) {
                $params[] = $unquote
                    ? self::unquoteString($param)
                    : $param;
            } elseif (Regex::match(
                '/^(' . self::HTTP_TOKEN . ')(?:\h*+=\h*+(.*))?$/D',
                $param,
                $matches,
            )) {
                $param = $matches[2] ?? '';
                $params[Str::lower($matches[1])] = $unquote
                    ? self::unquoteString($param)
                    : $param;
            } elseif ($strict) {
                throw new InvalidHeaderException(
                    sprintf('Invalid HTTP header parameter: %s', $param),
                );
            }
        }
        return $params ?? [];
    }

    /**
     * Merge parameters into a semicolon-delimited header value
     *
     * @param string[] $parameters
     */
    public static function mergeParameters(array $parameters): string
    {
        if (!$parameters) {
            return '';
        }

        foreach ($parameters as $key => $param) {
            $value = is_int($key) ? [] : [$key];
            if ($param !== '') {
                $value[] = self::maybeQuoteString($param);
            }
            $merged[] = $last = implode('=', $value);
        }
        $merged = implode('; ', $merged);
        // @phpstan-ignore notIdentical.alwaysTrue (`$merged` may be empty)
        return $last === '' && $merged !== ''
            ? substr($merged, 0, -1)
            : $merged;
    }

    /**
     * Get a product identifier suitable for User-Agent and Server headers as
     * per [RFC7231] Section 5.5.3
     */
    public static function getProduct(): string
    {
        return sprintf(
            '%s/%s php/%s',
            str_replace('/', '~', Package::name()),
            Package::version(true, true),
            \PHP_VERSION,
        );
    }

    /**
     * Get the media type of a multipart stream
     */
    public static function getMultipartMediaType(MultipartStreamInterface $stream): string
    {
        return sprintf(
            '%s; boundary=%s',
            self::TYPE_FORM_MULTIPART,
            self::maybeQuoteString($stream->getBoundary()),
        );
    }

    /**
     * Escape and double-quote a string unless it is a valid HTTP token, as per
     * [RFC7230] Section 3.2.6
     */
    public static function maybeQuoteString(string $string): string
    {
        return Regex::match(self::HTTP_TOKEN_REGEX, $string)
            ? $string
            : self::quoteString($string);
    }

    /**
     * Escape and double-quote a string as per [RFC7230] Section 3.2.6
     */
    public static function quoteString(string $string): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\"'], $string) . '"';
    }

    /**
     * Unescape and remove quotes from a string as per [RFC7230] Section 3.2.6
     */
    public static function unquoteString(string $string): string
    {
        $string = Regex::replace('/^"(.*)"$/D', '$1', $string, -1, $count);
        return $count
            ? Regex::replace('/\\\\(.)/', '$1', $string)
            : $string;
    }

    /**
     * Merge values into the query string of a request or URI
     *
     * @template T of PsrRequestInterface|PsrUriInterface|Stringable|string
     *
     * @param T $value
     * @param mixed[] $data
     * @param int-mask-of<HttpUtil::DATA_*> $flags
     * @return (T is PsrRequestInterface|PsrUriInterface ? T : Uri)
     */
    public static function mergeQuery(
        $value,
        array $data,
        int $flags = HttpUtil::DATA_PRESERVE_NUMERIC_KEYS | HttpUtil::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ) {
        /** @todo Replace with `parse_str()` alternative */
        parse_str(($value instanceof PsrRequestInterface
            ? $value->getUri()
            : ($value instanceof PsrUriInterface
                ? $value
                : new Uri((string) $value)))->getQuery(), $query);
        $data = array_replace_recursive($query, $data);
        return self::replaceQuery($value, $data, $flags, $dateFormatter);
    }

    /**
     * Replace the query string of a request or URI with the given values
     *
     * @template T of PsrRequestInterface|PsrUriInterface|Stringable|string
     *
     * @param T $value
     * @param mixed[] $data
     * @param int-mask-of<HttpUtil::DATA_*> $flags
     * @return (T is PsrRequestInterface|PsrUriInterface ? T : Uri)
     */
    public static function replaceQuery(
        $value,
        array $data,
        int $flags = HttpUtil::DATA_PRESERVE_NUMERIC_KEYS | HttpUtil::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ) {
        $query = (new FormData($data))->getQuery($flags, $dateFormatter);
        return $value instanceof PsrRequestInterface
            ? $value->withUri($value->getUri()->withQuery($query))
            : ($value instanceof PsrUriInterface
                ? $value
                : new Uri((string) $value))->withQuery($query);
    }

    /**
     * Iterate over name-value pairs in arrays with "name" and "value" keys
     *
     * @param iterable<array{name:string,value:string}> $items
     * @return iterable<string,string>
     */
    public static function getNameValuePairs(iterable $items): iterable
    {
        foreach ($items as $item) {
            yield $item['name'] => $item['value'];
        }
    }

    /**
     * Get the contents of a stream
     */
    public static function getStreamContents(PsrStreamInterface $from): string
    {
        $buffer = '';
        while (!$from->eof()) {
            $data = $from->read(1048576);
            if ($data === '') {
                break;
            }
            $buffer .= $data;
        }
        return $buffer;
    }

    /**
     * Copy the contents of one stream to another
     */
    public static function copyStream(PsrStreamInterface $from, PsrStreamInterface $to): void
    {
        $buffer = '';
        while (!$from->eof()) {
            $data = $from->read(8192);
            if ($data === '') {
                break;
            }
            $buffer .= $data;
            $buffer = substr($buffer, $to->write($buffer));
        }
        while ($buffer !== '') {
            $buffer = substr($buffer, $to->write($buffer));
        }
    }
}
