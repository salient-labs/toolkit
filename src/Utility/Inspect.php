<?php declare(strict_types=1);

namespace Lkrms\Utility;

/**
 * Get information from (or about) data
 *
 */
final class Inspect
{
    /**
     * Get the end-of-line sequence used in a string
     *
     * Recognised line endings are LF (`"\n"`), CRLF (`"\r\n"`) and CR (`"\r"`).
     *
     * @return string|null `null` if there are no recognised line breaks in
     * `$string`.
     *
     * @see Filesystem::getEol()
     * @see Str::setEol()
     */
    public static function getEol(string $string): ?string
    {
        $lfPos = strpos($string, "\n");
        if ($lfPos === false) {
            return strpos($string, "\r") === false
                ? null
                : "\r";
        }
        if ($lfPos && $string[$lfPos - 1] === "\r") {
            return "\r\n";
        }

        return "\n";
    }
}
