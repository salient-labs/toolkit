<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Salient\Core\Utility\Str;
use Salient\Core\AbstractDictionary;

/**
 * Frequently-used MIME types
 *
 * @extends AbstractDictionary<string>
 */
final class MimeType extends AbstractDictionary
{
    public const TEXT = 'text/plain';
    public const BINARY = 'application/octet-stream';
    public const WWW_FORM = 'application/x-www-form-urlencoded';
    public const JSON = 'application/json';

    /**
     * @var array<string,string>
     *
     * @link https://www.iana.org/assignments/media-type-structured-suffix/media-type-structured-suffix.xhtml
     */
    private static $SuffixMap = [
        MimeType::JSON => 'json',
    ];

    /**
     * True if a value is equal or equivalent to a known MIME type
     *
     * Returns `true` if `$value` isn't recognised but `$mimeType` maps to one
     * of its suffixes, e.g. if `$mimeType` is `application/json` and `$value`
     * is `application/jwk-set+json`.
     */
    public static function is(string $mimeType, string $value): bool
    {
        // Remove charset, boundary, etc.
        [$value] = explode(';', $value);
        $value = Str::lower(rtrim($value));

        if ($value === $mimeType) {
            return true;
        }

        // Bail out if there are no known suffixes for this MIME type
        if (!($suffix = self::$SuffixMap[$mimeType] ?? null)) {
            return false;
        }

        $suffixes = explode('+', $value);
        array_shift($suffixes);

        return in_array($suffix, $suffixes, true);
    }
}
