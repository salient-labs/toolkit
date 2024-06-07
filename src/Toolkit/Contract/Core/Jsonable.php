<?php declare(strict_types=1);

namespace Salient\Contract\Core;

interface Jsonable
{
    /**
     * Get a JSON representation of the object
     *
     * @param int-mask-of<\JSON_FORCE_OBJECT|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_NUMERIC_CHECK|\JSON_PRESERVE_ZERO_FRACTION|\JSON_PRETTY_PRINT|\JSON_THROW_ON_ERROR|\JSON_UNESCAPED_SLASHES|\JSON_UNESCAPED_UNICODE> $flags Passed
     * to {@see json_encode()}.
     */
    public function toJson(int $flags = 0): string;
}
