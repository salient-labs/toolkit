<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;

/**
 * Standardise calls to json_encode() and json_decode()
 */
final class Json extends Utility
{
    /**
     * Flags passed to json_encode()
     */
    public const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Flags passed to json_decode()
     */
    public const DECODE_FLAGS = JSON_THROW_ON_ERROR;

    /**
     * Convert a value to a JSON string
     *
     * @param mixed $value
     * @param int-mask-of<\JSON_FORCE_OBJECT|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_NUMERIC_CHECK|\JSON_PRESERVE_ZERO_FRACTION> $flags
     */
    public static function stringify($value, int $flags = 0): string
    {
        return json_encode($value, self::ENCODE_FLAGS | $flags);
    }

    /**
     * Convert a value to a human-readable JSON string
     *
     * @param mixed $value
     * @param int-mask-of<\JSON_FORCE_OBJECT|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_NUMERIC_CHECK|\JSON_PRESERVE_ZERO_FRACTION> $flags
     */
    public static function prettyPrint($value, int $flags = 0): string
    {
        return json_encode($value, self::ENCODE_FLAGS | JSON_PRETTY_PRINT | $flags);
    }

    /**
     * Convert a JSON string to a value
     *
     * @param int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE> $flags
     * @return mixed
     */
    public static function parse(string $json, int $flags = 0)
    {
        return json_decode($json, false, 512, self::DECODE_FLAGS | $flags);
    }

    /**
     * Convert a JSON string to a value where JSON objects are represented as
     * associative arrays
     *
     * @param int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE> $flags
     * @return mixed
     */
    public static function toArrays(string $json, int $flags = 0)
    {
        return json_decode($json, true, 512, self::DECODE_FLAGS | $flags);
    }
}
