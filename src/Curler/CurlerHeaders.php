<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Concern\TMutable;
use Lkrms\Curler\CurlerHeadersFlag as Flag;

/**
 * A collection of HTTP headers
 *
 */
class CurlerHeaders
{
    use TMutable;

    /**
     * Headers in their original order, case preserved, duplicates allowed
     *
     * @var CurlerHeader[]
     */
    private $Headers = [];

    /**
     * @var int
     */
    private $NextHeader = 0;

    /**
     * Lowercase header names => $Headers keys
     *
     * @var array<string,int[]>
     */
    private $HeaderKeysByName = [];

    /**
     * @var int|null
     */
    private $LastRawHeaderKey;

    /**
     * @var bool|null
     */
    private $RawHeadersClosed;

    /**
     * @var string[]
     */
    private $Trailers = [];

    /**
     * @var string[]
     */
    private $PrivateHeaderNames = [
        "authorization",
        "proxy-authorization",
    ];

    /**
     * @internal
     * @return $this
     */
    public function addRawHeader(string $line)
    {
        return $this->getMutable()->_addRawHeader($line);
    }

    /**
     * @return $this
     */
    public function addHeader(string $name, string $value, bool $private = false)
    {
        return $this->getMutable()->_addHeader($name, $value, $private);
    }

    /**
     * @return $this
     */
    public function unsetHeader(string $name)
    {
        return $this->getMutable()->_unsetHeader($name);
    }

    /**
     * @return $this
     */
    public function setHeader(string $name, string $value, bool $private = false)
    {
        return $this->unsetHeader($name)->_addHeader($name, $value, $private);
    }

    /**
     * @return $this
     */
    public function addPrivateHeaderName(string $name)
    {
        return $this->getMutable()->_addPrivateHeaderName($name);
    }

    /**
     * @return $this
     */
    private function _addRawHeader(string $line)
    {
        if ($this->RawHeadersClosed)
        {
            $this->Trailers[] = $line;

            return $this;
        }

        if (!trim($line))
        {
            $this->LastRawHeaderKey = null;
            $this->RawHeadersClosed = true;

            return $this;
        }

        // Remove trailing newlines, but keep other whitespace
        $line = rtrim($line, "\r\n");

        // HTTP headers can extend over multiple lines by starting each extra
        // line with horizontal whitespace, so if the line starts with SP or
        // HTAB, add it to the previous header
        if (strpos(" \t", $line[0]) !== false)
        {
            if (!is_null($key = $this->LastRawHeaderKey))
            {
                $this->Headers[$key] = $this->Headers[$key]->extendValue($line);
            }

            return $this;
        }

        if (count($split = explode(":", $line, 2)) == 2)
        {
            // The header name will only need trimming if there is whitespace
            // between it and ":", which is not allowed since [RFC7230] (see
            // Section 3.2.4) and should be removed from upstream responses
            $this->LastRawHeaderKey = $this->NextHeader;
            $this->_addHeader(rtrim($split[0]), ltrim($split[1]), false);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function _addHeader(string $name, string $value, bool $private)
    {
        $i = $this->NextHeader++;
        $this->Headers[$i] = new CurlerHeader($name, $value, $i);
        $this->HeaderKeysByName[strtolower($name)][] = $i;
        if ($private)
        {
            return $this->_addPrivateHeaderName($name);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function _unsetHeader(string $name)
    {
        $lower = strtolower($name);
        foreach (($this->HeaderKeysByName[$lower] ?? []) as $key)
        {
            unset($this->Headers[$key]);
        }
        unset($this->HeaderKeysByName[$lower]);

        return $this;
    }

    /**
     * @return $this
     */
    private function _addPrivateHeaderName(string $name)
    {
        $lower = strtolower($name);
        if (!in_array($lower, $this->PrivateHeaderNames))
        {
            $this->PrivateHeaderNames[] = $lower;
        }

        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->HeaderKeysByName);
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return array_values(array_map(
            fn(CurlerHeader $header) => $header->getHeader(),
            $this->Headers
        ));
    }

    /**
     * Get the value of a header
     *
     * @param int $flags A bitmask of {@see Flag} values.
     * @return string[]|string|null If {@see Flag::COMBINE_REPEATED} or
     * {@see Flag::DISCARD_REPEATED} are set:
     * - a `string` containing one or more comma-separated values, or
     * - `null` if there are no matching headers
     *
     * Otherwise:
     * - a `string[]` containing one or more values, or
     * - an empty `array` if there are no matching headers
     */
    public function getHeaderValue(string $name, int $flags = 0)
    {
        $values = array_map(
            fn(CurlerHeader $header) => $header->Value,
            array_intersect_key($this->Headers, array_flip($this->HeaderKeysByName[strtolower($name)] ?? []))
        );
        if (!($flags & (Flag::COMBINE_REPEATED | Flag::DISCARD_REPEATED)))
        {
            return $values;
        }
        if (!$values)
        {
            return null;
        }
        if ($flags & Flag::DISCARD_REPEATED)
        {
            return end($values);
        }
        return implode(", ", $values);
    }

    /**
     * Get the values of all headers
     *
     * @param int $flags A bitmask of {@see Flag} values.
     * @return array<string,string[]|string> An array that maps lowercase header
     * names to values returned by {@see CurlerHeaders::getHeaderValue()},
     * sorted to maintain the position of each header's last appearance if
     * {@see Flag::SORT_BY_LAST} is set.
     */
    public function getHeaderValues(int $flags = 0): array
    {
        if ($flags & Flag::SORT_BY_LAST)
        {
            $keysByName = $this->HeaderKeysByName;
            uasort($keysByName, fn(array $a, array $b) => end($a) <=> end($b));
        }
        $names = array_keys($keysByName ?? $this->HeaderKeysByName);

        return array_combine(
            $names,
            array_map(
                fn($name) => $this->getHeaderValue($name, $flags),
                $names
            )
        );
    }

    /**
     * @return string[]
     */
    public function getPublicHeaders(): array
    {
        return array_values(array_map(
            fn(CurlerHeader $header) => $header->getHeader(),
            array_filter(
                $this->Headers,
                fn(CurlerHeader $header) => !in_array(
                    strtolower($header->Name),
                    $this->PrivateHeaderNames
                )
            )
        ));
    }

    final protected function toImmutable(): CurlerHeadersImmutable
    {
        $immutable = new CurlerHeadersImmutable();
        foreach ($this as $property => $value)
        {
            $immutable->$property = $value;
        }

        return $immutable;
    }

}
