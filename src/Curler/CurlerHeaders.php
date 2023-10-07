<?php declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Auth\AccessToken;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Support\CurlerHeader;
use Lkrms\Curler\CurlerHeadersFlag as Flag;
use Lkrms\Support\Catalog\HttpHeader;

/**
 * A collection of HTTP headers
 */
final class CurlerHeaders implements ICurlerHeaders
{
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
        'authorization',
        'proxy-authorization',
    ];

    public static function create(): self
    {
        return new self();
    }

    public function addRawHeader(string $line)
    {
        return (clone $this)->_addRawHeader($line);
    }

    public function addHeader(string $name, string $value, bool $private = false)
    {
        return (clone $this)->_addHeader($name, $value, $private);
    }

    public function unsetHeader(string $name, ?string $pattern = null)
    {
        return (clone $this)->_unsetHeader($name, $pattern);
    }

    public function setHeader(string $name, string $value, bool $private = false)
    {
        return $this->unsetHeader($name)->_addHeader($name, $value, $private);
    }

    public function applyAccessToken(AccessToken $token, string $name = HttpHeader::AUTHORIZATION)
    {
        return $this->setHeader($name, sprintf('%s %s', $token->Type, $token->Token));
    }

    public function addPrivateHeaderName(string $name)
    {
        return (clone $this)->_addPrivateHeaderName($name);
    }

    /**
     * @return $this
     */
    private function _addRawHeader(string $line)
    {
        if ($this->RawHeadersClosed) {
            $this->Trailers[] = $line;

            return $this;
        }

        if (!trim($line)) {
            $this->LastRawHeaderKey = null;
            $this->RawHeadersClosed = true;

            return $this;
        }

        // Remove trailing newlines, but keep other whitespace
        $line = rtrim($line, "\r\n");

        // HTTP headers can extend over multiple lines by starting each extra
        // line with horizontal whitespace, so if the line starts with SP or
        // HTAB, add it to the previous header
        if (strpos(" \t", $line[0]) !== false) {
            if (!is_null($key = $this->LastRawHeaderKey)) {
                $this->Headers[$key] = $this->Headers[$key]->withValueExtended($line);
            }

            return $this;
        }

        if (count($split = explode(':', $line, 2)) == 2) {
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
        if ($private) {
            return $this->_addPrivateHeaderName($name);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function _unsetHeader(string $name, ?string $pattern)
    {
        $lower = strtolower($name);
        foreach (($this->HeaderKeysByName[$lower] ?? []) as $i => $key) {
            if ($pattern && !preg_match($pattern, $this->Headers[$key]->Value)) {
                continue;
            }
            unset($this->Headers[$key]);
            unset($this->HeaderKeysByName[$lower][$i]);
        }
        if (!($this->HeaderKeysByName[$lower] ?? null)) {
            unset($this->HeaderKeysByName[$lower]);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function _addPrivateHeaderName(string $name)
    {
        $lower = strtolower($name);
        if (!in_array($lower, $this->PrivateHeaderNames)) {
            $this->PrivateHeaderNames[] = $lower;
        }

        return $this;
    }

    public function hasHeader(string $name, ?string $pattern = null): bool
    {
        $name = strtolower($name);
        if (!array_key_exists($name, $this->HeaderKeysByName)) {
            return false;
        }
        if (!$pattern) {
            return true;
        }
        foreach ($this->HeaderKeysByName[$name] as $key) {
            if (preg_match($pattern, $this->Headers[$key]->Value)) {
                return true;
            }
        }

        return false;
    }

    public function getHeaders(): array
    {
        return array_values(array_map(
            fn(CurlerHeader $header) => $header->getHeader(),
            $this->Headers
        ));
    }

    public function getHeaderValue(string $name, int $flags = 0, ?string $pattern = null)
    {
        $values = array_map(
            fn(CurlerHeader $header) => $header->Value,
            array_intersect_key($this->Headers, array_flip($this->HeaderKeysByName[strtolower($name)] ?? []))
        );
        if ($pattern) {
            $values = array_filter(
                $values,
                fn(string $header) => (bool) preg_match($pattern, $header)
            );
        }

        // If no flags are set, return "a `string[]` containing one or more
        // values, or an empty `array` if there are no matching headers"
        if (!($flags & (Flag::COMBINE | Flag::KEEP_LAST | Flag::KEEP_FIRST))) {
            return $values;
        }

        // Otherwise, return "`null` if there are no matching headers"
        if (!$values) {
            return null;
        }

        // Or "a `string` containing one or more comma-separated values"
        if ($flags & Flag::KEEP_LAST) {
            return end($values);
        }

        if ($flags & Flag::KEEP_FIRST) {
            return reset($values);
        }

        return implode(', ', $values);
    }

    public function getHeaderValues(int $flags = 0): array
    {
        $names = array_keys($this->HeaderKeysByName);

        return array_combine(
            $names,
            array_map(
                fn($name) => $this->getHeaderValue($name, $flags),
                $names
            )
        );
    }

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

    public function getTrailers(): array
    {
        return $this->Trailers;
    }
}
