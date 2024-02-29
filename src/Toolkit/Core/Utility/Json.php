<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Contract\Core\JsonDecodeFlag;
use Salient\Contract\Core\JsonEncodeFlag;
use Salient\Core\AbstractUtility;

/**
 * Wrappers for json_encode() and json_decode()
 */
final class Json extends AbstractUtility
{
    /**
     * Flags passed to json_encode()
     */
    public const ENCODE_FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    /**
     * Flags passed to json_decode()
     */
    public const DECODE_FLAGS = \JSON_THROW_ON_ERROR;

    /**
     * Convert a value to a JSON string
     *
     * @param mixed $value
     * @param int-mask-of<JsonEncodeFlag::*> $flags
     */
    public static function stringify($value, int $flags = 0): string
    {
        return json_encode($value, self::ENCODE_FLAGS | $flags);
    }

    /**
     * Convert a value to a human-readable JSON string
     *
     * @param mixed $value
     * @param int-mask-of<JsonEncodeFlag::*> $flags
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
     * @param int-mask-of<JsonDecodeFlag::*> $flags
     * @return mixed
     */
    public static function parse(string $json, int $flags = 0)
    {
        return json_decode($json, null, 512, self::DECODE_FLAGS | $flags);
    }

    /**
     * Convert a JSON string to a value where JSON objects are represented as
     * associative arrays
     *
     * @param int-mask-of<JsonDecodeFlag::*> $flags
     * @return mixed
     */
    public static function parseObjectAsArray(string $json, int $flags = 0)
    {
        return json_decode($json, true, 512, self::DECODE_FLAGS | $flags);
    }
}
