<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

use Lkrms\Facade\Convert;
use Lkrms\Support\Catalog\RegularExpression as Regex;

/**
 * A tag extracted from a PHP DocBlock
 *
 */
class PhpDocTag
{
    /**
     * @var string
     */
    public $Tag;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var string|null
     */
    public $Type;

    /**
     * @var string|null
     */
    public $Description;

    public function __construct(
        string $tag,
        ?string $name = null,
        ?string $type = null,
        ?string $description = null,
        bool $legacyNullable = false
    ) {
        $this->Tag = $tag;
        $this->Name = $name === null ? null : self::sanitiseString($name);
        $this->Type = $type === null ? null : self::normaliseType($type, $legacyNullable);
        $this->Description = $description === null ? null : self::sanitiseString($description);
    }

    /**
     * Add missing values from a PhpDocTag that represents the same entity in a
     * parent class or interface
     *
     * @param static $parent
     * @return $this
     */
    public function mergeInherited($parent)
    {
        PhpDoc::mergeValue($this->Type, $parent->Type);
        PhpDoc::mergeValue($this->Description, $parent->Description);

        return $this;
    }

    /**
     * Normalise a PHPDoc type
     *
     * @param bool $legacyNullable If `true`, nullable types are returned as
     * `"?<type>"` instead of `"<type>|null"`.
     */
    final public static function normaliseType(?string $type, bool $legacyNullable = false): ?string
    {
        if (!($type = self::sanitiseString($type))) {
            return null;
        }

        $replace = [
            '/\bclass-string<mixed>/i' => 'class-string',
        ];
        $replace =
            fn(array $types): array =>
                preg_replace(array_keys($replace), array_values($replace), $types);

        if (!preg_match(
            Regex::anchorAndDelimit(Regex::PHPDOC_TYPE, '/', false),
            trim($type),
            $matches
        )) {
            return $replace([$type])[0];
        }

        $types = Convert::splitAndTrimOutsideBrackets('|', $type);

        // Move `null` to the end of union types
        $notNull = array_filter(
            array_map(
                fn(string $t): string => ltrim($t, '?'),
                $types
            ),
            fn(string $t): bool => (bool) strcasecmp($t, 'null')
        );
        if ($notNull !== $types) {
            $types = $notNull;
            $nullable = true;
        }

        // Simplify composite types
        $phpTypeRegex = Regex::anchorAndDelimit(Regex::PHP_TYPE);
        foreach ($types as &$type) {
            $brackets = false;
            if ($type && $type[0] === '(' && $type[-1] === ')') {
                $brackets = true;
                $type = substr($type, 1, -1);
            }
            $type = implode('&', $_types = array_unique($replace(explode('&', $type))));
            if ($brackets && (count($_types) > 1 ||
                    !preg_match($phpTypeRegex, $type))) {
                $type = "($type)";
            }
        }
        $types = array_unique($replace($types));
        if ($nullable ?? false) {
            if ($legacyNullable && count($types) === 1) {
                $types[0] = '?' . $types[0];
            } else {
                $types[] = 'null';
            }
        }

        return implode('|', $types);
    }

    /**
     * Return null if a string is null, empty, or only contains whitespace,
     * otherwise remove whitespace from the end of the string
     *
     */
    final public static function sanitiseString(?string $string): ?string
    {
        return $string ? (rtrim($string) ?: null) : null;
    }
}
