<?php declare(strict_types=1);

namespace Lkrms\Utility;

use ReflectionClass;

/**
 * Get information from (or about) data
 */
final class Inspect
{
    /**
     * Get the type of a variable
     *
     * @param mixed $value
     */
    public static function getType($value): string
    {
        if (is_object($value)) {
            return (new ReflectionClass($value))->isAnonymous()
                ? 'class@anonymous'
                : get_class($value);
        }
        if (is_resource($value)) {
            return sprintf('resource (%s)', get_resource_type($value));
        }

        $type = gettype($value);
        return [
            'boolean' => 'bool',
            'integer' => 'int',
            'double' => 'float',
            'NULL' => 'null',
        ][$type] ?? $type;
    }

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
