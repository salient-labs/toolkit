<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Utility\Get;

/**
 * @internal
 */
trait MemberDataTrait
{
    public bool $Hide = false;

    /**
     * @param mixed $value
     */
    private static function getValueCode($value, bool $declared): string
    {
        $code = Get::code($value);

        if (mb_strlen($code) > 20) {
            if ($declared) {
                if (is_array($value)) {
                    return Get::code($value, ",\n");
                }
            } elseif (is_array($value) || is_string($value)) {
                return '<' . Get::type($value) . '>';
            }
        }

        return $code;
    }
}
