<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\HasFormDataFlag;
use Salient\Contract\Http\HasMediaType;
use Salient\Contract\Http\HasRequestMethod;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Date;
use Salient\Utility\Package;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use InvalidArgumentException;
use Stringable;

final class HttpUtil extends AbstractUtility implements
    HasFormDataFlag,
    HasMediaType,
    HasRequestMethod
{
    /**
     * @link https://www.iana.org/assignments/media-type-structured-suffix/media-type-structured-suffix.xhtml
     *
     * @var array<string,string>
     */
    private const SUFFIX_TYPE = [
        'gzip' => self::TYPE_GZIP,
        'json' => self::TYPE_JSON,
        'jwt' => self::TYPE_JWT,
        'xml' => self::TYPE_XML,
        'yaml' => self::TYPE_YAML,
        'zip' => self::TYPE_ZIP,
    ];

    /**
     * @var array<string,string>
     */
    private const ALIAS_TYPE = [
        'text/xml' => self::TYPE_XML,
    ];

    /**
     * Check if a string is a valid HTTP request method
     *
     * @phpstan-assert-if-true HttpUtil::METHOD_* $method
     */
    public static function isRequestMethod(string $method): bool
    {
        return Reflect::hasConstantWithValue(HasRequestMethod::class, $method);
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
        [$type] = explode(';', $type);
        $type = Str::lower(rtrim($type));

        if ((self::ALIAS_TYPE[$type] ?? $type) === $mimeType) {
            return true;
        }

        // Check for a structured syntax suffix
        $pos = strrpos($type, '+');
        if ($pos === false) {
            return false;
        }

        $suffix = substr($type, $pos + 1);
        $type = substr($type, 0, $pos);

        return (self::ALIAS_TYPE[$type] ?? $type) === $mimeType
            || (self::SUFFIX_TYPE[$suffix] ?? null) === $mimeType;
    }

    /**
     * Get an HTTP date value as per [RFC7231] Section 7.1.1.1
     */
    public static function getDate(?DateTimeInterface $date = null): string
    {
        return ($date ? Date::immutable($date) : new DateTimeImmutable())
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DateTimeInterface::RFC7231);
    }

    /**
     * Get semicolon-delimited parameters from the value of an HTTP header
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
                continue;
            }
            if (Regex::match('/^(' . Regex::HTTP_TOKEN . ')(?:\h*+=\h*+(.*))?$/D', $param, $matches)) {
                $param = $matches[2] ?? '';
                $params[Str::lower($matches[1])] = $unquote
                    ? self::unquoteString($param)
                    : $param;
                continue;
            }
            if ($strict) {
                throw new InvalidArgumentException(sprintf('Invalid parameter: %s', $param));
            }
        }
        return $params ?? [];
    }

    /**
     * Merge parameters into a semicolon-delimited HTTP header value
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
        // @phpstan-ignore notIdentical.alwaysTrue ($merged could be empty)
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
     * Escape and quote a string unless it is a valid HTTP token, as per
     * [RFC7230] Section 3.2.6
     */
    public static function maybeQuoteString(string $string): string
    {
        return Regex::match('/^' . Regex::HTTP_TOKEN . '$/D', $string)
            ? $string
            : '"' . self::escapeQuotedString($string) . '"';
    }

    /**
     * Escape backslashes and double-quote marks in a string as per [RFC7230]
     * Section 3.2.6
     */
    public static function escapeQuotedString(string $string): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $string);
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
     * @template T of RequestInterface|PsrUriInterface|Stringable|string
     *
     * @param T $value
     * @param mixed[] $data
     * @param int-mask-of<HttpUtil::PRESERVE_*> $flags
     * @return (T is RequestInterface|PsrUriInterface ? T : Uri)
     */
    public static function mergeQuery(
        $value,
        array $data,
        int $flags = HttpUtil::PRESERVE_NUMERIC_KEYS | HttpUtil::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ) {
        if ($value instanceof RequestInterface) {
            $uri = $value->getUri();
            $return = $value;
        } elseif ($value instanceof PsrUriInterface) {
            $return = $uri = $value;
        } else {
            $return = $uri = new Uri((string) $value);
        }

        /** @todo Replace with `parse_str()` alternative */
        parse_str($uri->getQuery(), $query);
        $query = (new FormData(array_replace_recursive($query, $data)))
            ->getQuery($flags, $dateFormatter);

        return $return instanceof RequestInterface
            ? $return->withUri($uri->withQuery($query))
            : $return->withQuery($query);
    }

    /**
     * Replace the query string of a request or URI with the given values
     *
     * @template T of RequestInterface|PsrUriInterface|Stringable|string
     *
     * @param T $value
     * @param mixed[] $data
     * @param int-mask-of<HttpUtil::PRESERVE_*> $flags
     * @return (T is RequestInterface|PsrUriInterface ? T : Uri)
     */
    public static function replaceQuery(
        $value,
        array $data,
        int $flags = HttpUtil::PRESERVE_NUMERIC_KEYS | HttpUtil::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ) {
        $query = (new FormData($data))->getQuery($flags, $dateFormatter);
        if ($value instanceof RequestInterface) {
            return $value->withUri($value->getUri()->withQuery($query));
        }
        if (!$value instanceof PsrUriInterface) {
            $value = new Uri((string) $value);
        }
        return $value->withQuery($query);
    }

    /**
     * Get key-value pairs from a list of arrays with "name" and "value" keys
     *
     * @param iterable<array{name:string,value:string}> $items
     * @return Generator<string,string>
     */
    public static function getNameValueGenerator(iterable $items): Generator
    {
        foreach ($items as $item) {
            yield $item['name'] => $item['value'];
        }
    }
}
