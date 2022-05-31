<?php

declare(strict_types=1);

namespace Lkrms\Curler;

/**
 * A collection of HTTP headers
 *
 */
class CurlerHeaders
{
    /**
     * Headers in their original order, case preserved, duplicates allowed
     *
     * @var CurlerHeader[]
     */
    private $Headers = [];

    /**
     * Lowercase header names => $Headers keys
     *
     * @var array<string,int[]>
     */
    private $HeaderKeysByName = [];

    /**
     * @var CurlerHeader|null
     */
    private $LastRawHeader;

    /**
     * @var bool
     */
    private $RawHeadersClosed = false;

    /**
     * @var string[]
     */
    private $Trailers = [];

    /**
     * @internal
     * @param string $line
     */
    public function addRawHeader(string $line): void
    {
        if ($this->RawHeadersClosed)
        {
            $this->Trailers[] = $line;
            return;
        }

        if (!trim($line))
        {
            $this->LastRawHeader    = null;
            $this->RawHeadersClosed = true;
            return;
        }

        // Remove trailing newlines, but keep other whitespace
        $line = rtrim($line, "\r\n");

        // HTTP headers can extend over multiple lines by starting each extra
        // line with horizontal whitespace, so if the line starts with SP or
        // HTAB, add it to the previous header
        if (strpos(" \t", $line[0]) !== false)
        {
            if ($this->LastRawHeader)
            {
                $this->LastRawHeader->extendValue($line);
            }
            return;
        }

        // A less forgiving alternative to the below:
        //
        //     if (preg_match('/^([!#$%&\'*+-.^_`|~0-9a-z]+):\h*(.*)(\h|$)/i', $line, $matches))
        //     {
        //         $this->LastRawHeader = $this->_addHeader($matches[1], $matches[2]);
        //     }
        if (count($split = explode(":", $line, 2)) == 2)
        {
            // The header name will only need trimming if there is whitespace
            // between it and ":", which is not allowed since RFC 7230 (see
            // Section 3.2.4) and should be removed from upstream responses
            $this->LastRawHeader = $this->_addHeader(trim($split[0]), ltrim($split[1]));
        }
    }

    private function _addHeader(string $name, string $value): CurlerHeader
    {
        $lower = strtolower($name);
        if (!array_key_exists($lower, $this->HeaderKeysByName))
        {
            $this->HeaderKeysByName[$lower] = [];
        }

        $this->Headers[] = $header = new CurlerHeader($name, $value);
        end($this->Headers);
        $this->HeaderKeysByName[$lower][] = key($this->Headers);

        return $header;
    }

    public function addHeader(string $name, string $value): void
    {
        $this->_addHeader($name, $value);
    }

    public function setHeader(string $name, string $value): void
    {
        $this->unsetHeader($name);
        $this->_addHeader($name, $value);
    }

    public function unsetHeader(string $name): void
    {
        $lower = strtolower($name);
        foreach (($this->HeaderKeysByName[$lower] ?? []) as $key)
        {
            unset($this->Headers[$key]);
        }
        unset($this->HeaderKeysByName[$lower]);
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
     * Get an array that maps header names to values
     *
     * @param bool $joinMultiple If `true`, multiple headers with the same name
     * will be combined into one header with comma-separated values. If `false`,
     * only the last of these headers will be returned.
     * @return array<string,string>
     */
    public function getHeadersByName($joinMultiple = true): array
    {
        return array_combine(
            array_keys($this->HeaderKeysByName),
            array_map(
                fn(array $keys) => $joinMultiple
                ? implode(", ", array_map(
                    fn(CurlerHeader $header) => $header->Value,
                    array_intersect_key($this->Headers, array_flip($keys))
                ))
                : $this->Headers[array_pop($keys)]->Value,
                $this->HeaderKeysByName
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
                    ["authorization"]
                )
            )
        ));
    }
}
