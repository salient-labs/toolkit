<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Concern\Immutable;
use Lkrms\Concern\ImmutableArrayAccess;
use Lkrms\Concern\TReadableCollection;
use Lkrms\Contract\Arrayable;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IImmutable;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Pcre;
use Generator;
use LogicException;

/**
 * A collection of HTTP headers
 *
 * @implements ICollection<string,string[]>
 */
class HttpHeaders implements ICollection, IImmutable
{
    /** @use TReadableCollection<string,string[]> */
    use TReadableCollection;
    /** @use ImmutableArrayAccess<string,string[]> */
    use ImmutableArrayAccess;
    use Immutable;

    /**
     * [ [ Name => value ], ... ]
     *
     * @var array<array<string,string>>
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
     * Parse and apply an HTTP header field
     *
     * @param bool $strict If `true`, an exception is thrown if `$line` is not
     * \[RFC7230]-compliant.
     * @return static
     */
    public function addLine(string $line, bool $strict = false)
    {
        if ($strict) {
            return $this->strictAddLine($line);
        }

        if (trim($line) === '') {
            return $this->close()->carry(null);
        }

        $line = rtrim($line, "\r\n");
        $carry = Pcre::match('/\h+$/', $line, $matches) ? $matches[0] : null;
        if (strpos(" \t", $line[0]) !== false) {
            // Section 3.2.4 of [RFC7230]: "A user agent that receives an
            // obs-fold in a response message that is not within a message/http
            // container MUST replace each received obs-fold with one or more SP
            // octets prior to interpreting the field value."
            $line = $this->Carry . ' ' . trim($line);
            return $this->extendLast($line)->carry($carry);
        }
        $split = explode(':', $line, 2);
        if (count($split) !== 2) {
            return $this->carry(null);
        }
        [$name, $value] = $split;
        // Whitespace between field name and ":" is not allowed since [RFC7230]
        // (see Section 3.2.4) and should be removed from upstream responses
        return $this
            ->add(rtrim($name), trim($value))
            ->maybeIndexTrailer()
            ->carry($carry);
    }

    /**
     * @return static
     */
    private function strictAddLine(string $line)
    {
        if (substr($line, -2) !== "\r\n") {
            throw new InvalidArgumentException('HTTP header field must end with CRLF');
        }

        if ($line === "\r\n") {
            if ($this->Closed) {
                throw new InvalidArgumentException('HTTP message cannot have empty line after body');
            }
            return $this->close()->carry(null);
        }

        $line = substr($line, 0, -2);
        if (!Pcre::match(
            Regex::anchorAndDelimit(Regex::HTTP_HEADER_FIELD),
            $line,
            $matches,
            PREG_UNMATCHED_AS_NULL
        ) || $matches['bad_whitespace'] !== null) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP header field: %s', $line));
        }
        $carry = $matches['carry'];
        if ($matches['extended'] !== null) {
            $line = $this->Carry . ' ' . $matches['extended'];
            return $this->extendLast($line)->carry($carry);
        }
        return $this
            ->add($matches['name'], $matches['value'])
            ->maybeIndexTrailer()
            ->carry($carry);
    }

    /**
     * Apply a value to a header, preserving any existing values
     *
     * @param string $key
     * @param string[]|string $value
     * @return static
     */
    public function add($key, $value)
    {
        $values = (array) $value;
        if (!$values) {
            return $this;
        }
        $lower = strtolower($key);
        $headers = $this->Headers;
        $index = $this->Index;
        $key = $this->normaliseName($key);
        foreach ($values as $value) {
            $headers[] = [$key => $this->normaliseValue($value)];
            $index[$lower][] = array_key_last($headers);
        }
        return $this->replaceHeaders($headers, $index);
    }

    /**
     * Apply a value to a header, replacing any existing values
     *
     * @param string $key
     * @param string[]|string $value
     * @return static
     */
    public function set($key, $value)
    {
        $lower = strtolower($key);
        $headers = $this->Headers;
        $index = $this->Index;
        $values = (array) $value;
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
        $key = $this->normaliseName($key);
        foreach ($values as $value) {
            $headers[] = [$key => $this->normaliseValue($value)];
            $index[$lower][] = array_key_last($headers);
        }
        return $this->replaceHeaders($headers, $index);
    }

    /**
     * Remove a header
     *
     * @param string $key
     * @return static
     */
    public function unset($key)
    {
        $lower = strtolower($key);
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
     * Apply values to headers from an array or Traversable, optionally
     * preserving existing values
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $items
     * @return static
     */
    public function merge($items, bool $preserveExisting = true)
    {
        $normalise = true;
        if ($items instanceof self) {
            $items = $items->headers();
            $normalise = false;
        }

        $headers = $this->Headers;
        $index = $this->Index;
        $applied = false;
        foreach ($items as $key => $value) {
            $lower = strtolower($key);
            $values = (array) $value;
            if (
                !$preserveExisting &&
                // Checking against $this->Index instead of $index means any
                // duplicates in $items will be preserved
                isset($this->Index[$lower]) &&
                !($unset[$lower] ?? false)
            ) {
                $unset[$lower] = true;
                foreach ($index[$lower] as $i) {
                    unset($headers[$i]);
                }
                if (!$values) {
                    unset($index[$lower]);
                    continue;
                }
                // Maintain the order of $index for comparison
                $index[$lower] = [];
            }
            if ($normalise) {
                $key = $this->normaliseName($key);
            }
            foreach ($values as $value) {
                $applied = true;
                $headers[] = [$key => $normalise ? $this->normaliseValue($value) : $value];
                $index[$lower][] = array_key_last($headers);
            }
        }

        if (
            ($preserveExisting && !$applied) ||
            $this->getIndexValues($headers, $index) === $this->getIndexValues($this->Headers, $this->Index)
        ) {
            return $this;
        }

        return $this->replaceHeaders($headers, $index);
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
    public function filter(callable $callback, int $mode = ICollection::CALLBACK_USE_VALUE)
    {
        $index = $this->Index;
        $prev = null;
        $item = null;
        $lower = null;
        $count = 0;
        $changed = false;

        foreach ($index as $nextLower => $headerIndex) {
            $nextKey = null;
            $nextValue = null;
            foreach ($headerIndex as $i) {
                $header = $this->Headers[$i];
                $value = reset($header);
                $nextKey ??= key($header);
                if ($mode === ICollection::CALLBACK_USE_KEY) {
                    break;
                }
                $nextValue[] = $value;
            }
            $next = $mode === ICollection::CALLBACK_USE_KEY
                ? $nextKey
                : ($mode === ICollection::CALLBACK_USE_BOTH
                    ? [$nextKey => $nextValue]
                    : $nextValue);
            if ($count++) {
                if (!$callback($item, $next, $prev)) {
                    unset($index[$lower]);
                    $changed = true;
                }
                $prev = $item;
            }
            $item = $next;
            $lower = $nextLower;
        }
        if ($count && !$callback($item, null, $prev)) {
            unset($index[$lower]);
            $changed = true;
        }

        return $changed ? $this->replaceHeaders(null, $index) : $this;
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
     *
     * @return array<string,string[]>
     */
    public function jsonSerialize(): array
    {
        return $this->Items;
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
     * Iterate over headers in their original order
     *
     * The original case of each header is preserved.
     *
     * @return Generator<string,string>
     */
    public function headers(): Generator
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

    protected function normaliseName(string $name): string
    {
        if (!Pcre::match(Regex::anchorAndDelimit(Regex::HTTP_HEADER_FIELD_NAME), $name)) {
            throw new InvalidArgumentException(sprintf('Invalid header name: %s', $name));
        }
        return $name;
    }

    protected function normaliseValue(string $value): string
    {
        $value = Pcre::replace('/\r\n\h+/', ' ', trim($value));
        if (!Pcre::match(Regex::anchorAndDelimit(Regex::HTTP_HEADER_FIELD_VALUE), $value)) {
            throw new InvalidArgumentException(sprintf('Invalid header value: %s', $value));
        }
        return $value;
    }

    /**
     * @param array<array<string,string>>|null $headers
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
     * @param array<array<string,string>>|null $headers
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
        $clone->Index = $index;
        $clone->Items = $clone->getHeaders();
        return $clone;
    }

    /**
     * Get an array that maps header names to values, preserving the original
     * case of the first appearance of each header
     *
     * @param bool|null $trailers If `false`, trailers are not returned. If
     * `true`, only trailers are returned. If `null` (the default), all headers
     * are returned.
     * @return array<string,string[]>
     */
    public function getHeaders(?bool $trailers = null): array
    {
        foreach ($this->Index as $headerIndex) {
            unset($key);
            foreach ($headerIndex as $i) {
                if ($trailers === false && ($this->Trailers[$i] ?? false)) {
                    continue;
                }
                if ($trailers === true && !($this->Trailers[$i] ?? false)) {
                    continue;
                }
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
     * @return array<array<string,string>>
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
     * @return static
     */
    protected function close()
    {
        if ($this->Closed) {
            return $this;
        }
        $clone = $this->clone();
        $clone->Closed = true;
        return $clone;
    }

    /**
     * @return static
     */
    protected function carry(?string $carry)
    {
        if ($this->Carry === $carry) {
            return $this;
        }
        $clone = $this->clone();
        $clone->Carry = $carry;
        return $clone;
    }

    /**
     * @return static
     */
    protected function extendLast(string $line)
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
    protected function maybeIndexTrailer()
    {
        if (!$this->Headers) {
            throw new LogicException('No headers applied');
        }
        if (!$this->Closed) {
            return $this;
        }
        $i = array_key_last($this->Headers);
        if ($this->Trailers[$i] ?? null) {
            return $this;
        }
        $clone = $this->clone();
        $clone->Trailers[$i] = true;
        return $clone;
    }
}
