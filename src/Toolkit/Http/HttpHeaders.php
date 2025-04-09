<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Salient\Collection\ReadOnlyArrayAccessTrait;
use Salient\Collection\ReadOnlyCollectionTrait;
use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\Message\MessageInterface;
use Salient\Contract\Http\CredentialInterface;
use Salient\Contract\Http\HeadersInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Http\Exception\InvalidHeaderException;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Test;
use InvalidArgumentException;
use IteratorAggregate;
use LogicException;

/**
 * An [RFC7230]-compliant HTTP header collection
 *
 * Headers can be applied explicitly or by passing HTTP header fields to
 * {@see HttpHeaders::addLine()}.
 *
 * @implements IteratorAggregate<string,string[]>
 */
class HttpHeaders implements HeadersInterface, IteratorAggregate
{
    /** @use ReadOnlyCollectionTrait<string,string[]> */
    use ReadOnlyCollectionTrait;
    /** @use ReadOnlyArrayAccessTrait<string,string[]> */
    use ReadOnlyArrayAccessTrait;
    use ImmutableTrait;

    private const HTTP_HEADER_FIELD_NAME = '/^[-0-9a-z!#$%&\'*+.^_`|~]++$/iD';
    private const HTTP_HEADER_FIELD_VALUE = '/^([\x21-\x7e\x80-\xff]++(?:\h++[\x21-\x7e\x80-\xff]++)*+)?$/D';

    private const HTTP_HEADER_FIELD = <<<'REGEX'
/ ^
(?(DEFINE)
  (?<token> [-0-9a-z!#$%&'*+.^_`|~]++ )
  (?<field_vchar> [\x21-\x7e\x80-\xff]++ )
  (?<field_content> (?&field_vchar) (?: \h++ (?&field_vchar) )*+ )
)
(?:
  (?<name> (?&token) ) (?<bad_whitespace> \h++ )?+ : \h*+ (?<value> (?&field_content)? ) |
  \h++ (?<extended> (?&field_content)? )
)
(?<carry> \h++ )?
$ /xiD
REGEX;

    /**
     * [ [ Name => value ], ... ]
     *
     * @var array<int,non-empty-array<string,string>>
     */
    protected array $Headers = [];

    /**
     * [ Lowercase name => [ index in $Headers, ... ] ]
     *
     * @var array<string,int[]>
     */
    protected array $Index = [];

    /**
     * [ Index in $Headers => true ]
     *
     * @var array<int,true>
     */
    protected array $Trailers = [];

    protected bool $Closed = false;

    /**
     * Trailing whitespace carried over from the previous line
     *
     * Applied before `obs-fold` when a header is extended over multiple lines.
     */
    protected ?string $Carry = null;

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     */
    public function __construct($items = [])
    {
        $this->Items = $this->filterByLower($this->getItemsArray($items, $headers, $index));
        $this->Index = $this->filterByLower($index);
        $this->Headers = $headers;
    }

    /**
     * Resolve a value to an HttpHeaders object
     *
     * If `$value` is a string, it is parsed as an HTTP message.
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|PsrMessageInterface|string $value
     * @return static
     */
    public static function from($value): self
    {
        if ($value instanceof static) {
            return $value;
        }
        if ($value instanceof MessageInterface) {
            return self::from($value->getInnerHeaders());
        }
        if ($value instanceof PsrMessageInterface) {
            return new static($value->getHeaders());
        }
        if (is_string($value)) {
            $lines =
                // Remove start line
                Arr::shift(
                    // Split on CRLF
                    explode(
                        "\r\n",
                        // Remove body if present
                        explode("\r\n\r\n", Str::setEol($value, "\r\n"), 2)[0] . "\r\n"
                    )
                );
            $instance = new static();
            foreach ($lines as $line) {
                $instance = $instance->addLine("$line\r\n");
            }
            return $instance;
        }
        return new static($value);
    }

    /**
     * Get the value of the Content-Length header, or null if it is not set
     *
     * @return int<0,max>|null
     * @throws InvalidHeaderException if `Content-Length` is given multiple
     * times or has an invalid value.
     */
    public function getContentLength(): ?int
    {
        if (!$this->hasHeader(self::HEADER_CONTENT_LENGTH)) {
            return null;
        }

        $length = $this->getOnlyHeaderValue(self::HEADER_CONTENT_LENGTH);
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
     * Get the value of the Content-Type header's boundary parameter, or null if
     * it is not set
     *
     * @throws InvalidHeaderException if `Content-Type` is given multiple times
     * or has an invalid value.
     */
    public function getMultipartBoundary(): ?string
    {
        if (!$this->hasHeader(self::HEADER_CONTENT_TYPE)) {
            return null;
        }

        try {
            return HttpUtil::getParameters(
                $this->getOnlyHeaderValue(self::HEADER_CONTENT_TYPE),
                false,
                false,
            )['boundary'] ?? null;
        } catch (InvalidArgumentException $ex) {
            throw new InvalidHeaderException($ex->getMessage());
        }
    }

    /**
     * Get preferences applied to the Prefer header as per [RFC7240]
     *
     * @return array<string,array{value:string,parameters:array<string,string>}>
     */
    public function getPreferences(): array
    {
        if (!$this->hasHeader(self::HEADER_PREFER)) {
            return [];
        }

        foreach ($this->getHeaderValues(self::HEADER_PREFER) as $pref) {
            /** @var array<string,string> */
            $params = HttpUtil::getParameters($pref, true);
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
            if (isset($prefs[$lower])) {
                continue;
            }
            $prefs[$lower] = HttpUtil::mergeParameters(
                is_string($pref)
                    ? [$name => $pref]
                    : [$name => $pref['value']] + ($pref['parameters'] ?? [])
            );
        }

        return implode(', ', $prefs ?? []);
    }

    /**
     * Get the value of the Retry-After header in seconds, or null if it has an
     * invalid value or is not set
     *
     * @return int<0,max>|null
     */
    public function getRetryAfter(): ?int
    {
        $after = $this->getHeaderLine(self::HEADER_RETRY_AFTER);
        if (Test::isInteger($after) && (int) $after >= 0) {
            return (int) $after;
        }

        $after = strtotime($after);
        if ($after === false) {
            return null;
        }

        return max(0, $after - time());
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return implode("\r\n", $this->getLines());
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException if `$strict` is `true` and `$line` is
     * not \[RFC7230]-compliant.
     */
    public function addLine(string $line, bool $strict = false)
    {
        if ($strict && substr($line, -2) !== "\r\n") {
            throw new InvalidArgumentException('HTTP header field must end with CRLF');
        }

        if ($line === "\r\n" || (!$strict && trim($line) === '')) {
            if ($strict && $this->Closed) {
                throw new InvalidArgumentException('HTTP message cannot have empty header after body');
            }
            return $this->with('Closed', true)->with('Carry', null);
        }

        $extend = false;
        $name = null;
        $value = null;
        if ($strict) {
            $line = substr($line, 0, -2);
            if (
                !Regex::match(self::HTTP_HEADER_FIELD, $line, $matches, \PREG_UNMATCHED_AS_NULL)
                || $matches['bad_whitespace'] !== null
            ) {
                throw new InvalidArgumentException(sprintf('Invalid HTTP header field: %s', $line));
            }
            // Section 3.2.4 of [RFC7230]: "A user agent that receives an
            // obs-fold in a response message that is not within a message/http
            // container MUST replace each received obs-fold with one or more SP
            // octets prior to interpreting the field value."
            $carry = $matches['carry'];
            if ($matches['extended'] !== null) {
                if (!$this->Headers) {
                    throw new InvalidArgumentException(sprintf('Invalid line folding: %s', $line));
                }
                $extend = true;
                $line = $this->Carry . ' ' . $matches['extended'];
            } else {
                $name = $matches['name'];
                $value = $matches['value'];
            }
        } else {
            $line = rtrim($line, "\r\n");
            $carry = Regex::match('/\h+$/', $line, $matches) ? $matches[0] : null;
            if (strpos(" \t", $line[0]) !== false) {
                $extend = true;
                $line = $this->Carry . ' ' . trim($line);
            } else {
                $split = explode(':', $line, 2);
                if (count($split) !== 2) {
                    return $this->with('Carry', null);
                }
                // Whitespace after name is not allowed since [RFC7230] (see
                // Section 3.2.4) and should be removed from upstream responses
                $name = rtrim($split[0]);
                $value = trim($split[1]);
            }
        }

        if ($extend) {
            return $this->extendLast($line)->with('Carry', $carry);
        }

        /** @var string $name */
        /** @var string $value */
        return $this->addValue($name, $value)->maybeIndexTrailer()->with('Carry', $carry);
    }

    /**
     * @inheritDoc
     */
    public function hasEmptyLine(): bool
    {
        return $this->Closed;
    }

    /**
     * @inheritDoc
     */
    public function addValue($key, $value)
    {
        $values = (array) $value;
        if (!$values) {
            throw new InvalidArgumentException(
                sprintf('At least one value must be given for HTTP header: %s', $key)
            );
        }
        $lower = Str::lower($key);
        $headers = $this->Headers;
        $index = $this->Index;
        $key = $this->filterName($key);
        foreach ($values as $value) {
            $headers[] = [$key => $this->filterValue($value)];
            $index[$lower][] = array_key_last($headers);
        }
        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value)
    {
        $values = (array) $value;
        if (!$values) {
            throw new InvalidArgumentException(
                sprintf('At least one value must be given for HTTP header: %s', $key)
            );
        }
        $lower = Str::lower($key);
        $headers = $this->Headers;
        $index = $this->Index;
        if (isset($index[$lower])) {
            // Return `$this` if existing values are being reapplied
            if (count($index[$lower]) === count($values)) {
                $headerIndex = $index[$lower];
                $changed = false;
                foreach ($values as $value) {
                    $i = array_shift($headerIndex);
                    if ($headers[$i] !== [$key => $value]) {
                        $changed = true;
                        break;
                    }
                }
                if (!$changed) {
                    return $this;
                }
            }
            foreach ($index[$lower] as $i) {
                unset($headers[$i]);
            }
            unset($index[$lower]);
        }
        $key = $this->filterName($key);
        foreach ($values as $value) {
            $headers[] = [$key => $this->filterValue($value)];
            $index[$lower][] = array_key_last($headers);
        }
        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @inheritDoc
     */
    public function unset($key)
    {
        $lower = Str::lower($key);
        if (!isset($this->Index[$lower])) {
            return $this;
        }
        $headers = $this->Headers;
        $index = $this->Index;
        foreach ($index[$lower] as $i) {
            unset($headers[$i]);
        }
        unset($index[$lower]);
        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @inheritDoc
     */
    public function merge($items, bool $preserveValues = false)
    {
        $headers = $this->Headers;
        $index = $this->Index;
        $applied = false;
        foreach ($this->getItems($items) as $key => $value) {
            $values = (array) $value;
            $lower = Str::lower($key);
            if (
                !$preserveValues
                // Checking against $this->Index instead of $index means any
                // duplicates in $items will be preserved
                && isset($this->Index[$lower])
                && !($unset[$lower] ?? false)
            ) {
                $unset[$lower] = true;
                foreach ($index[$lower] as $i) {
                    unset($headers[$i]);
                }
                // Maintain the order of $index for comparison
                $index[$lower] = [];
            }
            foreach ($values as $value) {
                $applied = true;
                $headers[] = [$key => $value];
                $index[$lower][] = array_key_last($headers);
            }
        }

        if (
            ($preserveValues && !$applied)
            || $this->getIndexValues($headers, $index) === $this->getIndexValues($this->Headers, $this->Index)
        ) {
            return $this;
        }

        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @inheritDoc
     */
    public function authorize(
        CredentialInterface $credential,
        string $headerName = HttpHeaders::HEADER_AUTHORIZATION
    ) {
        return $this->set($headerName, sprintf(
            '%s %s',
            $credential->getAuthenticationScheme(),
            $credential->getCredential(),
        ));
    }

    /**
     * @inheritDoc
     */
    public function normalise()
    {
        return $this->maybeReplaceHeaders($this->Headers, $this->Index, true);
    }

    /**
     * @inheritDoc
     */
    public function sort()
    {
        return $this->maybeReplaceHeaders(
            Arr::sort($this->Headers, true, fn($a, $b) => array_key_first($a) <=> array_key_first($b)),
            Arr::sortByKey($this->Index),
            true,
        );
    }

    /**
     * @inheritDoc
     */
    public function reverse()
    {
        return $this->maybeReplaceHeaders(
            array_reverse($this->Headers, true),
            array_reverse($this->Index, true),
            true,
        );
    }

    /**
     * @inheritDoc
     */
    public function map(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $items = [];
        $prev = null;
        $item = null;
        $key = null;

        foreach ($this->Index as $nextLower => $headerIndex) {
            $nextValue = [];
            $nextKey = null;
            foreach ($headerIndex as $i) {
                $header = $this->Headers[$i];
                $nextValue[] = reset($header);
                $nextKey ??= key($header);
            }
            $next = $this->getCallbackValue($mode, $nextLower, $nextValue);
            if ($item !== null) {
                /** @disregard P1006 */
                foreach ($callback($item, $next, $prev) as $value) {
                    /** @var string $key */
                    $items[$key][] = $value;
                }
                $prev = $item;
            }
            $item = $next;
            $key = $nextKey ?? $nextLower;
        }
        if ($item !== null) {
            /** @disregard P1006 */
            foreach ($callback($item, null, $prev) as $value) {
                /** @var string $key */
                $items[$key][] = $value;
            }
        }

        return new static($items);
    }

    /**
     * @inheritDoc
     */
    public function filter(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $index = $this->Index;
        $prev = null;
        $item = null;
        $lower = null;
        $changed = false;

        foreach ($index as $nextLower => $headerIndex) {
            $nextValue = [];
            foreach ($headerIndex as $i) {
                $header = $this->Headers[$i];
                $nextValue[] = reset($header);
            }
            $next = $this->getCallbackValue($mode, $nextLower, $nextValue);
            if ($item !== null) {
                if (!$callback($item, $next, $prev)) {
                    unset($index[$lower]);
                    $changed = true;
                }
                $prev = $item;
            }
            $item = $next;
            $lower = $nextLower;
        }
        if ($item !== null && !$callback($item, null, $prev)) {
            unset($index[$lower]);
            $changed = true;
        }

        return $changed ? $this->replaceHeaders(null, $index) : $this;
    }

    /**
     * @inheritDoc
     */
    public function only(array $keys)
    {
        return $this->onlyIn(Arr::toIndex($keys));
    }

    /**
     * @inheritDoc
     */
    public function onlyIn(array $index)
    {
        return $this->maybeReplaceHeaders(
            null,
            array_intersect_key(
                $this->Index,
                array_change_key_case($index)
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function except(array $keys)
    {
        return $this->exceptIn(Arr::toIndex($keys));
    }

    /**
     * @inheritDoc
     */
    public function exceptIn(array $index)
    {
        return $this->maybeReplaceHeaders(
            null,
            array_diff_key(
                $this->Index,
                array_change_key_case($index)
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function slice(int $offset, ?int $length = null)
    {
        return $this->maybeReplaceHeaders(
            null,
            array_slice($this->Index, $offset, $length, true)
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray(bool $preserveKeys = true): array
    {
        return $preserveKeys
            ? $this->Items
            : array_values($this->Items);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        foreach ($this->headers() as $name => $value) {
            $headers[] = [
                'name' => $name,
                'value' => $value,
            ];
        }
        return $headers ?? [];
    }

    /**
     * @inheritDoc
     */
    public function pop(&$last = null)
    {
        if (!$this->Index) {
            $last = null;
            return $this;
        }
        $index = $this->Index;
        array_pop($index);
        $last = Arr::last($this->Items);
        return $this->replaceHeaders(null, $index);
    }

    /**
     * @inheritDoc
     */
    public function shift(&$first = null)
    {
        if (!$this->Index) {
            $first = null;
            return $this;
        }
        $index = $this->Index;
        array_shift($index);
        $first = Arr::first($this->Items);
        return $this->replaceHeaders(null, $index);
    }

    /**
     * @inheritDoc
     */
    public function trailers()
    {
        return $this->whereIsTrailer();
    }

    /**
     * @inheritDoc
     */
    public function withoutTrailers()
    {
        return $this->whereIsTrailer(false);
    }

    /**
     * @inheritDoc
     */
    public function getLines(
        string $format = '%s: %s',
        ?string $emptyFormat = null
    ): array {
        foreach ($this->headers() as $key => $value) {
            if ($emptyFormat !== null && trim($value) === '') {
                $lines[] = sprintf($emptyFormat, $key, '');
                continue;
            }
            $lines[] = sprintf($format, $key, $value);
        }
        return $lines ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->doGetHeaders(true);
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLines(): array
    {
        foreach ($this->Items as $lower => $values) {
            $lines[$lower] = implode(',', $values);
        }
        return $lines ?? [];
    }

    /**
     * @inheritDoc
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->Items[Str::lower($name)]);
    }

    /**
     * @inheritDoc
     */
    public function getHeader(string $name): array
    {
        return $this->Items[Str::lower($name)] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine(string $name): string
    {
        return $this->doGetHeaderValue($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeaderValues(string $name): array
    {
        $values = $this->Items[Str::lower($name)] ?? [];
        if (!$values) {
            return [];
        }
        // [RFC7230], Section 7: "a recipient MUST parse and ignore a reasonable
        // number of empty list elements"
        return Str::splitDelimited(',', implode(',', $values));
    }

    /**
     * @inheritDoc
     */
    public function getFirstHeaderValue(string $name): string
    {
        return $this->doGetHeaderValue($name, true);
    }

    /**
     * @inheritDoc
     */
    public function getLastHeaderValue(string $name): string
    {
        return $this->doGetHeaderValue($name, false, true);
    }

    /**
     * @inheritDoc
     */
    public function getOnlyHeaderValue(string $name, bool $orSame = false): string
    {
        return $this->doGetHeaderValue($name, false, false, true, $orSame);
    }

    private function doGetHeaderValue(
        string $name,
        bool $first = false,
        bool $last = false,
        bool $only = false,
        bool $orSame = false
    ): string {
        $values = $this->getHeaderValues($name);
        if (!$values) {
            return '';
        }
        if ($first) {
            return reset($values);
        }
        if ($last) {
            return end($values);
        }
        if ($only) {
            if ($orSame) {
                $values = array_unique($values);
            }
            if (count($values) > 1) {
                throw new InvalidHeaderException(sprintf(
                    'HTTP header has more than one value: %s',
                    $name,
                ));
            }
            return reset($values);
        }
        return implode(', ', $values);
    }

    /**
     * @return iterable<string,string>
     */
    protected function headers(): iterable
    {
        foreach ($this->Headers as $header) {
            $value = reset($header);
            $key = key($header);
            yield $key => $value;
        }
    }

    /**
     * @param string[] $a
     * @param string[] $b
     */
    protected function compareItems($a, $b): int
    {
        return $a <=> $b;
    }

    protected function filterName(string $name): string
    {
        if (!Regex::match(self::HTTP_HEADER_FIELD_NAME, $name)) {
            throw new InvalidArgumentException(sprintf('Invalid header name: %s', $name));
        }
        return $name;
    }

    protected function filterValue(string $value): string
    {
        $value = Regex::replace('/\r\n\h+/', ' ', trim($value, " \t"));
        if (!Regex::match(self::HTTP_HEADER_FIELD_VALUE, $value)) {
            throw new InvalidArgumentException(sprintf('Invalid header value: %s', $value));
        }
        return $value;
    }

    /**
     * @param array<int,non-empty-array<string,string>>|null $headers
     * @param array<string,int[]> $index
     * @return static
     */
    protected function maybeReplaceHeaders(?array $headers, array $index, bool $filterHeaders = false)
    {
        $headers ??= $this->getIndexHeaders($index);

        if ($filterHeaders) {
            $headers = $this->filterHeaders($headers);
        }

        if ($headers === $this->Headers && $index === $this->Index) {
            return $this;
        }

        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @param array<int,non-empty-array<string,string>>|null $headers
     * @param array<string,int[]> $index
     * @return static
     */
    protected function replaceHeaders(?array $headers, array $index)
    {
        $headers ??= $this->getIndexHeaders($index);

        $clone = clone $this;
        $clone->Headers = $headers;
        $clone->Index = $clone->filterByLower($index);
        $clone->Items = $clone->doGetHeaders();
        return $clone;
    }

    /**
     * @return array<string,string[]>
     */
    protected function doGetHeaders(bool $preserveCase = false): array
    {
        foreach ($this->Index as $lower => $headerIndex) {
            if ($preserveCase) {
                unset($key);
            } else {
                $key = $lower;
            }
            foreach ($headerIndex as $i) {
                $header = $this->Headers[$i];
                $value = reset($header);
                $key ??= key($header);
                $headers[$key][] = $value;
            }
        }
        return $headers ?? [];
    }

    /**
     * @param array<string,int[]> $index
     * @return array<int,non-empty-array<string,string>>
     */
    protected function getIndexHeaders(array $index): array
    {
        foreach ($index as $headerIndex) {
            foreach ($headerIndex as $i) {
                $headers[$i] = null;
            }
        }
        return array_intersect_key($this->Headers, $headers ?? []);
    }

    /**
     * @param array<int,non-empty-array<string,string>> $headers
     * @return array<int,non-empty-array<string,string>>
     */
    private function filterHeaders(array $headers): array
    {
        $host = [];
        foreach ($headers as $i => $header) {
            if (isset(array_change_key_case($header)['host'])) {
                $host[$i] = $header;
            }
        }
        return $host + $headers;
    }

    /**
     * @template T
     *
     * @param array<string,T> $index
     * @return array<string,T>
     */
    private function filterByLower(array $index): array
    {
        // According to [RFC7230] Section 5.4, "a user agent SHOULD generate
        // Host as the first header field following the request-line"
        if (isset($index['host'])) {
            $index = ['host' => $index['host']] + $index;
        }
        return $index;
    }

    /**
     * @param array<array<string,string>> $headers
     * @param array<string,int[]> $index
     * @return array<string,array<array<string,string>>>
     */
    protected function getIndexValues(array $headers, array $index): array
    {
        foreach ($index as $lower => $headerIndex) {
            foreach ($headerIndex as $i) {
                $_headers[$lower][] = $headers[$i];
            }
        }
        return $_headers ?? [];
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     * @param array<int,non-empty-array<string,string>>|null $headers
     * @param array<string,int[]>|null $index
     * @param-out array<int,non-empty-array<string,string>> $headers
     * @param-out array<string,int[]> $index
     * @return array<string,string[]>
     */
    protected function getItemsArray($items, ?array &$headers = null, ?array &$index = null): array
    {
        $headers = [];
        $index = [];
        $i = -1;
        foreach ($this->getItems($items) as $key => $values) {
            $lower = Str::lower($key);
            foreach ($values as $value) {
                $headers[++$i] = [$key => $value];
                $index[$lower][] = $i;
                $array[$lower][] = $value;
            }
        }

        return $array ?? [];
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     * @return iterable<string,string[]>
     */
    protected function getItems($items): iterable
    {
        if ($items instanceof self) {
            $items = $items->headers();
        } elseif ($items instanceof Arrayable) {
            /** @var array<string,string[]|string> */
            $items = $items->toArray();
        }
        yield from $this->filterItems($items);
    }

    /**
     * @param iterable<string,string[]|string> $items
     * @return iterable<string,string[]>
     */
    protected function filterItems(iterable $items): iterable
    {
        foreach ($items as $key => $value) {
            $values = (array) $value;
            if (!$values) {
                throw new InvalidArgumentException(sprintf(
                    'At least one value must be given for HTTP header: %s',
                    $key,
                ));
            }
            $key = $this->filterName($key);
            $filtered = [];
            foreach ($values as $value) {
                $filtered[] = $this->filterValue($value);
            }
            yield $key => $filtered;
        }
    }

    /**
     * @return static
     */
    private function extendLast(string $line)
    {
        if (!$this->Headers) {
            return $this;
        }
        $headers = $this->Headers;
        $i = array_key_last($headers);
        $header = $this->Headers[$i];
        $value = reset($header);
        $key = key($header);
        $headers[$i][$key] = ltrim($value . $line);
        return $this->maybeReplaceHeaders($headers, $this->Index);
    }

    /**
     * @return static
     */
    private function maybeIndexTrailer()
    {
        if (!$this->Headers) {
            // @codeCoverageIgnoreStart
            throw new LogicException('No headers applied');
            // @codeCoverageIgnoreEnd
        }
        if (!$this->Closed) {
            return $this;
        }
        $i = array_key_last($this->Headers);
        $trailers = $this->Trailers;
        $trailers[$i] = true;
        return $this->with('Trailers', $trailers);
    }

    /**
     * @return static
     */
    private function whereIsTrailer(bool $value = true)
    {
        $headers = [];
        $index = [];
        foreach ($this->Index as $lower => $headerIndex) {
            foreach ($headerIndex as $i) {
                $isTrailer = $this->Trailers[$i] ?? false;
                if ($value xor $isTrailer) {
                    continue;
                }
                $headers[$i] = $this->Headers[$i];
                $index[$lower][] = $i;
            }
        }
        ksort($headers);
        return $this->maybeReplaceHeaders($headers, $index);
    }
}
