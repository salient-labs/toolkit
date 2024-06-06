<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\AbstractUtility;

/**
 * Wrappers for json_encode() and json_decode()
 *
 * @api
 */
final class Json extends AbstractUtility
{
    /**
     * Flags always passed to json_encode()
     */
    public const ENCODE_FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    /**
     * Flags always passed to json_decode()
     */
    public const DECODE_FLAGS = \JSON_THROW_ON_ERROR;

    /**
     * Convert a value to a JSON string
     *
     * @param mixed $value
     * @param int-mask-of<\JSON_FORCE_OBJECT|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_NUMERIC_CHECK|\JSON_PRESERVE_ZERO_FRACTION|\JSON_PRETTY_PRINT|\JSON_THROW_ON_ERROR|\JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE> $flags
     */
    public static function stringify($value, int $flags = 0): string
    {
        return json_encode($value, self::ENCODE_FLAGS | $flags);
    }

    /**
     * Convert a value to a human-readable JSON string with native line endings
     *
     * @param mixed $value
     * @param int-mask-of<\JSON_FORCE_OBJECT|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_NUMERIC_CHECK|\JSON_PRESERVE_ZERO_FRACTION|\JSON_THROW_ON_ERROR|\JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE> $flags
     */
    public static function prettyPrint($value, int $flags = 0): string
    {
        return Str::eolToNative(
            json_encode($value, self::ENCODE_FLAGS | \JSON_PRETTY_PRINT | $flags)
        );
    }

    /**
     * Convert a JSON string to a value
     *
     * @param int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_OBJECT_AS_ARRAY|\JSON_THROW_ON_ERROR> $flags
     * @return mixed
     */
    public static function parse(string $json, int $flags = 0)
    {
        return json_decode($json, null, 512, self::DECODE_FLAGS | $flags);
    }

    /**
     * Convert a JSON string to a value, returning JSON objects as associative
     * arrays
     *
     * @param int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_THROW_ON_ERROR> $flags
     * @return mixed[]|int|float|string|bool|null
     */
    public static function parseObjectAsArray(string $json, int $flags = 0)
    {
        /** @var mixed[]|int|float|string|bool|null */
        return json_decode($json, true, 512, self::DECODE_FLAGS | $flags);
    }
}
