<?php declare(strict_types=1);

namespace Salient\Http;

use Salient\Collection\ReadableCollectionTrait;
use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Concern\ImmutableArrayAccessTrait;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Http\Exception\InvalidHeaderException;
use Generator;
use LogicException;

/**
 * An [RFC7230]-compliant HTTP header collection
 *
 * Headers can be applied explicitly or by passing HTTP header fields to
 * {@see HttpHeaders::addLine()}.
 *
 * @api
 */
class HttpHeaders implements HttpHeadersInterface
{
    /** @use ReadableCollectionTrait<string,string[]> */
    use ReadableCollectionTrait;
    /** @use ImmutableArrayAccessTrait<string,string[]> */
    use ImmutableArrayAccessTrait;
    use HasImmutableProperties {
        withPropertyValue as with;
    }

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
     * @var array<non-empty-array<string,string>>
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
     * Creates a new HttpHeaders object
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     */
    public function __construct($items = [])
    {
        $headers = [];
        $index = [];
        $i = -1;
        foreach ($this->generateItems($items) as $key => $value) {
            $values = (array) $value;
            $lower = Str::lower($key);
            foreach ($values as $value) {
                $headers[++$i] = [$key => $value];
                $index[$lower][] = $i;
            }
        }
        $this->Headers = $headers;
        $this->Index = $this->filterIndex($index);
        $this->Items = $this->doGetHeaders();
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
            if (!Pcre::match(self::HTTP_HEADER_FIELD, $line, $matches, \PREG_UNMATCHED_AS_NULL) ||
                    $matches['bad_whitespace'] !== null) {
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
            $carry = Pcre::match('/\h+$/', $line, $matches) ? $matches[0] : null;
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
        return $this->add($name, $value)->maybeIndexTrailer()->with('Carry', $carry);
    }

    /**
     * @inheritDoc
     */
    public function hasLastLine(): bool
    {
        return $this->Closed;
    }

    /**
     * @inheritDoc
     */
    public function add($key, $value)
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
    public function merge($items, bool $addToExisting = false)
    {
        $headers = $this->Headers;
        $index = $this->Index;
        $applied = false;
        foreach ($this->generateItems($items) as $key => $value) {
            $values = (array) $value;
            $lower = Str::lower($key);
            if (
                !$addToExisting &&
                // Checking against $this->Index instead of $index means any
                // duplicates in $items will be preserved
                isset($this->Index[$lower]) &&
                !($unset[$lower] ?? false)
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
            ($addToExisting && !$applied) ||
            $this->getIndexValues($headers, $index) === $this->getIndexValues($this->Headers, $this->Index)
        ) {
            return $this;
        }

        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @inheritDoc
     */
    public function authorize(
        AccessTokenInterface $token,
        string $headerName = HttpHeader::AUTHORIZATION
    ) {
        return $this->set(
            $headerName,
            sprintf('%s %s', $token->getTokenType(), $token->getToken())
        );
    }

    /**
     * @inheritDoc
     */
    public function sort()
    {
        return $this->maybeReplaceHeaders(
            $this->Headers,
            Arr::sortByKey($this->Index)
        );
    }

    /**
     * @inheritDoc
     */
    public function reverse()
    {
        return $this->maybeReplaceHeaders(
            $this->Headers,
            array_reverse($this->Index, true)
        );
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
        $count = 0;
        $changed = false;

        foreach ($index as $nextLower => $headerIndex) {
            $nextValue = null;
            foreach ($headerIndex as $i) {
                $header = $this->Headers[$i];
                $value = reset($header);
                if ($mode === CollectionInterface::CALLBACK_USE_KEY) {
                    break;
                }
                $nextValue[] = $value;
            }
            $next = $mode === CollectionInterface::CALLBACK_USE_KEY
                ? $nextLower
                : ($mode === CollectionInterface::CALLBACK_USE_BOTH
                    ? [$nextLower => $nextValue]
                    : $nextValue);
            if ($count++) {
                /** @var string[]|string|array<string,string[]> $item */
                /** @var string[]|string|array<string,string[]> $next */
                /** @var string[]|string|array<string,string[]>|null $prev */
                if (!$callback($item, $next, $prev)) {
                    unset($index[$lower]);
                    $changed = true;
                }
                $prev = $item;
            }
            $item = $next;
            $lower = $nextLower;
        }
        /** @var string[]|string|array<string,string[]> $item */
        /** @var string[]|string|array<string,string[]> $prev */
        if ($count && !$callback($item, null, $prev)) {
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
    public function toArray(): array
    {
        return $this->Items;
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
    public function getLines(string $format = '%s: %s'): array
    {
        foreach ($this->headers() as $key => $value) {
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
        return $this->doGetHeaderLine($name);
    }

    /**
     * @inheritDoc
     */
    public function getFirstHeaderLine(string $name): string
    {
        return $this->doGetHeaderLine($name, true);
    }

    /**
     * @inheritDoc
     */
    public function getLastHeaderLine(string $name): string
    {
        return $this->doGetHeaderLine($name, false, true);
    }

    /**
     * @inheritDoc
     */
    public function getOneHeaderLine(string $name): string
    {
        return $this->doGetHeaderLine($name, false, false, true);
    }

    private function doGetHeaderLine(
        string $name,
        bool $first = false,
        bool $last = false,
        bool $one = false
    ): string {
        $values = $this->Items[Str::lower($name)] ?? [];
        if (!$values) {
            return '';
        }
        if ($first) {
            return reset($values);
        }
        if ($last) {
            return end($values);
        }
        if ($one && count($values) > 1) {
            throw new InvalidHeaderException(sprintf(
                'HTTP header appears more than once: %s',
                $name,
            ));
        }
        return implode(',', $values);
    }

    /**
     * @return Generator<string,string>
     */
    protected function headers(): Generator
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
        if (!Pcre::match(self::HTTP_HEADER_FIELD_NAME, $name)) {
            throw new InvalidArgumentException(sprintf('Invalid header name: %s', $name));
        }
        return $name;
    }

    protected function filterValue(string $value): string
    {
        $value = Pcre::replace('/\r\n\h+/', ' ', trim($value, " \t"));
        if (!Pcre::match(self::HTTP_HEADER_FIELD_VALUE, $value)) {
            throw new InvalidArgumentException(sprintf('Invalid header value: %s', $value));
        }
        return $value;
    }

    /**
     * @param array<non-empty-array<string,string>>|null $headers
     * @param array<string,int[]> $index
     * @return static
     */
    protected function maybeReplaceHeaders(?array $headers, array $index)
    {
        if ($headers === null) {
            $headers = $this->getIndexHeaders($index);
        }

        if ($headers === $this->Headers && $index === $this->Index) {
            return $this;
        }

        return $this->replaceHeaders($headers, $index);
    }

    /**
     * @param array<non-empty-array<string,string>>|null $headers
     * @param array<string,int[]> $index
     * @return static
     */
    protected function replaceHeaders(?array $headers, array $index)
    {
        if ($headers === null) {
            $headers = $this->getIndexHeaders($index);
        }

        $clone = $this->clone();
        $clone->Headers = $headers;
        $clone->Index = $clone->filterIndex($index);
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
     * @return array<non-empty-array<string,string>>
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
     * @param array<string,int[]> $index
     * @return array<string,int[]>
     */
    private function filterIndex(array $index): array
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
     * @return Generator<string,string[]|string>
     */
    protected function generateItems($items): Generator
    {
        if ($items instanceof self) {
            yield from $items->headers();
        } elseif ($items instanceof Arrayable) {
            /** @var array<string,string[]|string> */
            $items = $items->toArray();
            yield from $this->filterItems($items);
        } elseif (is_array($items)) {
            yield from $this->filterItems($items);
        } else {
            foreach ($items as $key => $value) {
                $values = (array) $value;
                if (!$values) {
                    throw new InvalidArgumentException(
                        sprintf('At least one value must be given for HTTP header: %s', $key)
                    );
                }
                $key = $this->filterName($key);
                foreach ($values as $value) {
                    yield $key => $this->filterValue($value);
                }
            }
        }
    }

    /**
     * @param array<string,string[]|string> $items
     * @return array<string,string[]>
     */
    protected function filterItems(array $items): array
    {
        foreach ($items as $key => $value) {
            $values = (array) $value;
            if (!$values) {
                throw new InvalidArgumentException(
                    sprintf('At least one value must be given for HTTP header: %s', $key)
                );
            }
            $key = $this->filterName($key);
            foreach ($values as $value) {
                $filtered[$key][] = $this->filterValue($value);
            }
        }
        return $filtered ?? [];
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     * @return never
     *
     * @codeCoverageIgnore
     */
    protected function getItems($items): array
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            ReadableCollectionTrait::class,
        );
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
