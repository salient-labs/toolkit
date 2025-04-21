<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Salient\Collection\ArrayableCollectionTrait;
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
use InvalidArgumentException;
use IteratorAggregate;
use LogicException;

/**
 * @api
 *
 * @implements IteratorAggregate<string,string[]>
 */
class Headers implements HeadersInterface, IteratorAggregate, HasHttpRegex
{
    /** @use ReadOnlyCollectionTrait<string,string[]> */
    use ReadOnlyCollectionTrait;
    /** @use ReadOnlyArrayAccessTrait<string,string[]> */
    use ReadOnlyArrayAccessTrait;
    /** @use ArrayableCollectionTrait<string,string[]> */
    use ArrayableCollectionTrait;
    use ImmutableTrait;

    /**
     * [ key => [ name, value ], ... ]
     *
     * @var array<int,array{string,string}>
     */
    private array $Headers;

    /**
     * [ lowercase name => [ key, ... ], ... ]
     *
     * @var array<string,int[]>
     */
    private array $Index;

    /**
     * [ key => true, ... ]
     *
     * @var array<int,true>
     */
    private array $TrailerIndex = [];

    private bool $IsParser;
    private bool $HasEmptyLine = false;

    /**
     * Trailing whitespace carried over from the previous line
     *
     * Inserted before `obs-fold` when a header extends over multiple lines.
     */
    private ?string $Carry = null;

    /**
     * @api
     */
    public function __construct($items = [])
    {
        $items = $this->getItemsArray($items, $headers, $index);
        $this->Headers = $headers;
        $this->Index = $this->filterIndex($index);
        $this->Items = $this->filterIndex($items);
        $this->IsParser = !func_num_args();
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|PsrMessageInterface|string $headersOrPayload
     * @return static
     */
    public static function from($headersOrPayload): self
    {
        if ($headersOrPayload instanceof static) {
            return $headersOrPayload;
        }
        if ($headersOrPayload instanceof MessageInterface) {
            return self::from($headersOrPayload->getInnerHeaders());
        }
        if ($headersOrPayload instanceof PsrMessageInterface) {
            return new static($headersOrPayload->getHeaders());
        }
        if (is_string($headersOrPayload)) {
            // Normalise line endings
            $headersOrPayload = Str::setEol($headersOrPayload, "\r\n");
            // Extract headers, split on CRLF and remove start line
            $lines = Arr::shift(explode(
                "\r\n",
                explode("\r\n\r\n", $headersOrPayload, 2)[0] . "\r\n",
            ));
            // Parse header lines
            $instance = new static();
            foreach ($lines as $line) {
                $instance = $instance->addLine("$line\r\n");
            }
            return $instance;
        }
        return new static($headersOrPayload);
    }

    /**
     * @inheritDoc
     *
     * @param bool $strict If `true`, strict \[RFC7230] compliance is enforced.
     */
    public function addLine(string $line, bool $strict = false)
    {
        if (!$this->IsParser) {
            throw new LogicException(sprintf(
                '%s::%s() cannot be used after headers are applied via another method',
                static::class,
                __FUNCTION__,
            ));
        }

        $instance = $this->doAddLine($line, $strict);
        $instance->IsParser = true;
        return $instance;
    }

    /**
     * @return static
     */
    private function doAddLine(string $line, bool $strict)
    {
        if ($strict && substr($line, -2) !== "\r\n") {
            throw new InvalidHeaderException(
                'HTTP header line must end with CRLF',
            );
        }

        if ($line === "\r\n" || (!$strict && trim($line) === '')) {
            if ($strict && $this->HasEmptyLine) {
                throw new InvalidHeaderException(
                    'HTTP message cannot have empty header line after body',
                );
            }
            return $this->with('Carry', null)->with('HasEmptyLine', true);
        }

        if ($strict) {
            $line = substr($line, 0, -2);
            if (
                !Regex::match(self::HTTP_HEADER_FIELD_REGEX, $line, $matches, \PREG_UNMATCHED_AS_NULL)
                || $matches['bad_whitespace'] !== null
            ) {
                throw new InvalidHeaderException(
                    sprintf('Invalid HTTP header field: %s', $line),
                );
            }

            // As per [RFC7230] Section 3.2.4, "replace each received obs-fold
            // with one or more SP octets prior to interpreting the field value"
            $instance = $this->with('Carry', (string) $matches['carry']);
            if ($matches['extended'] !== null) {
                if ($this->Carry === null) {
                    throw new InvalidHeaderException(
                        sprintf('Invalid HTTP header line folding: %s', $line),
                    );
                }
                $line = $this->Carry . ' ' . $matches['extended'];
                return $instance->extendPreviousLine($line);
            }

            /** @var string */
            $name = $matches['name'];
            /** @var string */
            $value = $matches['value'];
            return $instance->addValue($name, $value)->maybeIndexLastHeader();
        }

        $line = rtrim($line, "\r\n");
        $carry = Regex::match('/\h++$/D', $line, $matches)
            ? $matches[0]
            : '';
        $instance = $this->with('Carry', $carry);
        if (strpos(" \t", $line[0]) !== false) {
            if ($this->Carry === null) {
                throw new InvalidHeaderException(
                    sprintf('Invalid HTTP header line folding: %s', $line),
                );
            }
            $line = $this->Carry . ' ' . trim($line);
            return $instance->extendPreviousLine($line);
        }

        $split = explode(':', $line, 2);
        if (count($split) !== 2) {
            throw new InvalidHeaderException(
                sprintf('Invalid HTTP header field: %s', $line),
            );
        }

        // [RFC7230] Section 3.2.4:
        // - "No whitespace is allowed between the header field-name and colon."
        // - "A proxy MUST remove any such whitespace from a response message
        //   before forwarding the message downstream."
        $name = rtrim($split[0]);
        $value = trim($split[1]);
        return $instance->addValue($name, $value)->maybeIndexLastHeader();
    }

    /**
     * @return static
     */
    private function extendPreviousLine(string $line)
    {
        /** @var non-empty-array<int,array{string,string}> */
        $headers = $this->Headers;
        $k = array_key_last($headers);
        [, $value] = $this->Headers[$k];
        $headers[$k][1] = ltrim($value . $line);
        return $this->maybeReplaceHeaders($headers, $this->Index);
    }

    /**
     * @return static
     */
    private function maybeIndexLastHeader()
    {
        if (!$this->HasEmptyLine) {
            return $this;
        }
        /** @var int */
        $k = array_key_last($this->Headers);
        $index = $this->TrailerIndex;
        $index[$k] = true;
        return $this->with('TrailerIndex', $index);
    }

    /**
     * @inheritDoc
     */
    public function hasEmptyLine(): bool
    {
        return $this->HasEmptyLine;
    }

    /**
     * @inheritDoc
     */
    public function addValue($key, $value)
    {
        $values = (array) $value;
        if (!$values) {
            throw new InvalidArgumentException(
                sprintf('No values given for header: %s', $key),
            );
        }
        $lower = Str::lower($key);
        $headers = $this->Headers;
        $index = $this->Index;
        $key = $this->filterName($key);
        foreach ($values as $value) {
            $headers[] = [$key, $this->filterValue($value)];
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
                sprintf('No values given for header: %s', $key),
            );
        }
        $lower = Str::lower($key);
        if (($this->Items[$lower] ?? []) === $values) {
            // Return `$this` if existing values are being reapplied
            return $this;
        }
        $headers = $this->Headers;
        $index = $this->Index;
        if (isset($index[$lower])) {
            foreach ($index[$lower] as $k) {
                unset($headers[$k]);
            }
            unset($index[$lower]);
        }
        $key = $this->filterName($key);
        foreach ($values as $value) {
            $headers[] = [$key, $this->filterValue($value)];
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
        if (!isset($this->Items[$lower])) {
            return $this;
        }
        $headers = $this->Headers;
        $index = $this->Index;
        foreach ($index[$lower] as $k) {
            unset($headers[$k]);
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
        $changed = false;
        foreach ($this->getItems($items) as $key => $values) {
            $lower = Str::lower($key);
            if (
                !$preserveValues
                // The same header may appear in `$items` multiple times, so
                // remove existing values once per pre-existing header only
                && isset($this->Items[$lower])
                && !isset($unset[$lower])
            ) {
                $unset[$lower] = true;
                foreach ($index[$lower] as $k) {
                    unset($headers[$k]);
                }
                // Maintain `$index` order for detection of reapplied values
                $index[$lower] = [];
            }
            foreach ($values as $value) {
                $headers[] = [$key, $value];
                $index[$lower][] = array_key_last($headers);
            }
            $changed = true;
        }

        return !$changed
            || $this->getIndexValues($headers, $index) === $this->doGetHeaders()
                ? $this
                : $this->replaceHeaders($headers, $index);
    }

    /**
     * @param array<int,array{string,string}> $headers
     * @param array<string,int[]> $index
     * @return array<string,string[]>
     */
    private function getIndexValues(array $headers, array $index): array
    {
        foreach ($index as $headerKeys) {
            $key = null;
            foreach ($headerKeys as $k) {
                $header = $headers[$k];
                // Preserve the case of the first appearance of each header
                $key ??= $header[0];
                $values[$key][] = $header[1];
            }
        }
        return $values ?? [];
    }

    /**
     * @inheritDoc
     */
    public function sort()
    {
        $headers = Arr::sort($this->Headers, true, fn($a, $b) => $a[0] <=> $b[0]);
        $index = Arr::sortByKey($this->Index);
        return $this->maybeReplaceHeaders($headers, $index, true);
    }

    /**
     * @inheritDoc
     */
    public function reverse()
    {
        $headers = array_reverse($this->Headers, true);
        $index = array_reverse($this->Index, true);
        return $this->maybeReplaceHeaders($headers, $index, true);
    }

    /**
     * @inheritDoc
     */
    public function map(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $prev = null;
        $item = null;
        $key = null;
        $items = [];

        foreach ($this->Index as $nextKey => $headerKeys) {
            $nextName = null;
            $nextValue = [];
            foreach ($headerKeys as $k) {
                $header = $this->Headers[$k];
                $nextName ??= $header[0];
                $nextValue[] = $header[1];
            }
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($item !== null) {
                /** @var string[] */
                $values = $callback($item, $next, $prev);
                /** @var string $key */
                $items[$key] = array_merge($items[$key], array_values($values));
            }
            $prev = $item;
            $item = $next;
            // Preserve the case of the first appearance of each header
            $key = $nextName ?? $nextKey;
            $items[$key] ??= [];
        }
        if ($item !== null) {
            /** @var string[] */
            $values = $callback($item, null, $prev);
            /** @var string $key */
            $items[$key] = array_merge($items[$key], array_values($values));
        }
        $items = array_filter($items);

        return Arr::same($items, $this->doGetHeaders())
            ? $this
            : new static($items);
    }

    /**
     * @inheritDoc
     */
    public function filter(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $index = $this->Index;
        $prev = null;
        $item = null;
        $key = null;
        $changed = false;

        foreach ($index as $nextKey => $headerKeys) {
            $nextValue = [];
            foreach ($headerKeys as $k) {
                $header = $this->Headers[$k];
                $nextValue[] = $header[1];
            }
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($item !== null && !$callback($item, $next, $prev)) {
                unset($index[$key]);
                $changed = true;
            }
            $prev = $item;
            $item = $next;
            $key = $nextKey;
        }
        if ($item !== null && !$callback($item, null, $prev)) {
            unset($index[$key]);
            $changed = true;
        }

        return $changed
            ? $this->replaceHeaders(null, $index)
            : $this;
    }

    /**
     * @inheritDoc
     */
    public function only(array $keys)
    {
        return $this->onlyIn(array_fill_keys($keys, true));
    }

    /**
     * @inheritDoc
     */
    public function onlyIn(array $index)
    {
        $index = array_change_key_case($index);
        $index = array_intersect_key($this->Index, $index);
        return $this->maybeReplaceHeaders(null, $index);
    }

    /**
     * @inheritDoc
     */
    public function except(array $keys)
    {
        return $this->exceptIn(array_fill_keys($keys, true));
    }

    /**
     * @inheritDoc
     */
    public function exceptIn(array $index)
    {
        $index = array_change_key_case($index);
        $index = array_diff_key($this->Index, $index);
        return $this->maybeReplaceHeaders(null, $index);
    }

    /**
     * @inheritDoc
     */
    public function slice(int $offset, ?int $length = null)
    {
        $index = array_slice($this->Index, $offset, $length, true);
        return $this->maybeReplaceHeaders(null, $index);
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
    public function authorize(
        CredentialInterface $credential,
        string $headerName = Headers::HEADER_AUTHORIZATION
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
    public function trailers()
    {
        return $this->whereHeaderIsTrailer(true);
    }

    /**
     * @inheritDoc
     */
    public function withoutTrailers()
    {
        return $this->whereHeaderIsTrailer(false);
    }

    /**
     * @return static
     */
    private function whereHeaderIsTrailer(bool $value)
    {
        $headers = [];
        $index = [];
        foreach ($this->Index as $lower => $headerKeys) {
            foreach ($headerKeys as $k) {
                $isTrailer = $this->TrailerIndex[$k] ?? false;
                if (!($value xor $isTrailer)) {
                    $headers[$k] = $this->Headers[$k];
                    $index[$lower][] = $k;
                }
            }
        }
        ksort($headers);
        return $this->maybeReplaceHeaders($headers, $index);
    }

    /**
     * @inheritDoc
     */
    public function getLines(
        string $format = '%s: %s',
        ?string $emptyFormat = null
    ): array {
        foreach ($this->generateHeaders() as $key => $value) {
            $lines[] = $emptyFormat !== null && trim($value) === ''
                ? sprintf($emptyFormat, $key, '')
                : sprintf($format, $key, $value);
        }
        return $lines ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->doGetHeaders();
    }

    /**
     * @return array<string,string[]>
     */
    private function doGetHeaders(bool $preserveCase = true): array
    {
        foreach ($this->Index as $lower => $headerKeys) {
            $key = $preserveCase
                ? null
                : $lower;
            foreach ($headerKeys as $k) {
                $header = $this->Headers[$k];
                $key ??= $header[0];
                $value = $header[1];
                $headers[$key][] = $value;
            }
        }
        return $headers ?? [];
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
        $values = $this->Items[Str::lower($name)] ?? [];
        return $values
            ? implode(', ', $values)
            : '';
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLines(): array
    {
        foreach ($this->Items as $lower => $values) {
            $lines[$lower] = implode(', ', $values);
        }
        return $lines ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderValues(string $name): array
    {
        $line = $this->getHeaderLine($name);
        return $line === ''
            ? []
            // [RFC7230] Section 7: "a recipient MUST parse and ignore a
            // reasonable number of empty list elements"
            : Str::splitDelimited(',', $line);
    }

    /**
     * @inheritDoc
     */
    public function getFirstHeaderValue(string $name): string
    {
        return $this->doGetHeaderValue($name, true, false);
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
        return $this->doGetHeaderValue($name, false, false, $orSame);
    }

    private function doGetHeaderValue(
        string $name,
        bool $first,
        bool $last,
        bool $orSame = false
    ): string {
        $values = $this->getHeaderValues($name);
        if (!$values) {
            return '';
        }
        if ($last) {
            return end($values);
        }
        if (!$first) {
            if ($orSame) {
                $values = array_unique($values);
            }
            if (count($values) > 1) {
                throw new InvalidHeaderException(
                    sprintf('HTTP header has more than one value: %s', $name),
                );
            }
        }
        return reset($values);
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
    public function jsonSerialize(): array
    {
        foreach ($this->generateHeaders() as $name => $value) {
            $headers[] = ['name' => $name, 'value' => $value];
        }
        return $headers ?? [];
    }

    /**
     * @param array<int,array{string,string}>|null $headers
     * @param array<string,int[]> $index
     * @return static
     */
    private function maybeReplaceHeaders(
        ?array $headers,
        array $index,
        bool $filterHeaders = false
    ) {
        $headers ??= $this->getIndexHeaders($index);

        if ($filterHeaders) {
            $headers = $this->filterHeaders($headers);
        }

        return $headers === $this->Headers
            && $index === $this->Index
                ? $this
                : $this->replaceHeaders($headers, $index);
    }

    /**
     * @param array<int,array{string,string}>|null $headers
     * @param array<string,int[]> $index
     * @return static
     */
    private function replaceHeaders(?array $headers, array $index)
    {
        $clone = clone $this;
        $clone->Headers = $headers ?? $this->getIndexHeaders($index);
        $clone->Index = $clone->filterIndex($index);
        $clone->Items = $clone->doGetHeaders(false);
        $clone->IsParser = false;
        return $clone;
    }

    /**
     * @param array<string,int[]> $index
     * @return array<int,array{string,string}>
     */
    private function getIndexHeaders(array $index): array
    {
        foreach ($index as $headerKeys) {
            foreach ($headerKeys as $k) {
                $headers[$k] = true;
            }
        }
        return array_intersect_key($this->Headers, $headers ?? []);
    }

    /**
     * @template T
     *
     * @param array<string,T> $index
     * @return array<string,T>
     */
    private function filterIndex(array $index): array
    {
        // [RFC7230] Section 5.4: "a user agent SHOULD generate Host as the
        // first header field following the request-line"
        return isset($index['host'])
            ? ['host' => $index['host']] + $index
            : $index;
    }

    /**
     * @param array<int,array{string,string}> $headers
     * @return array<int,array{string,string}>
     */
    private function filterHeaders(array $headers): array
    {
        $host = [];
        foreach ($headers as $k => $header) {
            if (Str::lower($header[0]) === 'host') {
                $host[$k] = $header;
            }
        }
        return $host + $headers;
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     * @param array<int,array{string,string}>|null $headers
     * @param array<string,int[]>|null $index
     * @param-out array<int,array{string,string}> $headers
     * @param-out array<string,int[]> $index
     * @return array<string,string[]>
     */
    protected function getItemsArray(
        $items,
        ?array &$headers = null,
        ?array &$index = null
    ): array {
        $headers = [];
        $index = [];
        $k = -1;
        foreach ($this->getItems($items) as $key => $values) {
            $lower = Str::lower($key);
            foreach ($values as $value) {
                $headers[++$k] = [$key, $value];
                $index[$lower][] = $k;
                $array[$lower][] = $value;
            }
        }
        return $array ?? [];
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     * @return iterable<string,non-empty-array<string>>
     */
    protected function getItems($items): iterable
    {
        if ($items instanceof self) {
            $items = $items->generateHeaders();
        } elseif ($items instanceof Arrayable) {
            $items = $items->toArray();
        }
        // @phpstan-ignore argument.type
        yield from $this->filterItems($items);
    }

    /**
     * @param iterable<string,string[]|string> $items
     * @return iterable<string,non-empty-array<string>>
     */
    private function filterItems(iterable $items): iterable
    {
        foreach ($items as $key => $value) {
            $values = (array) $value;
            if (!$values) {
                throw new InvalidArgumentException(
                    sprintf('No values given for header: %s', $key),
                );
            }
            $key = $this->filterName($key);
            $filtered = [];
            foreach ($values as $value) {
                $filtered[] = $this->filterValue($value);
            }
            yield $key => $filtered;
        }
    }

    private function filterName(string $name): string
    {
        if (!Regex::match(self::HTTP_HEADER_FIELD_NAME_REGEX, $name)) {
            throw new InvalidArgumentException(
                sprintf('Invalid header name: %s', $name),
            );
        }
        return $name;
    }

    private function filterValue(string $value): string
    {
        $value = Regex::replace('/\r\n\h++/', ' ', trim($value, " \t"));
        if (!Regex::match(self::HTTP_HEADER_FIELD_VALUE_REGEX, $value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid header value: %s', $value),
            );
        }
        return $value;
    }

    /**
     * @param string[] $a
     * @param string[] $b
     */
    protected function compareItems($a, $b): int
    {
        return $a <=> $b;
    }

    /**
     * @return iterable<string,string>
     */
    protected function generateHeaders(): iterable
    {
        foreach ($this->Headers as [$key, $value]) {
            yield $key => $value;
        }
    }
}
