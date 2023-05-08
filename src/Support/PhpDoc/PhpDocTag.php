<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

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

        // Normalise nullable types, e.g. `?string` and `null|string`
        $nullable = preg_replace('/^(\?|null\|)|\|null$/', '', $type, -1, $count);
        if ($count && preg_match(Regex::anchorAndDelimit(Regex::PHP_TYPE), $nullable)) {
            return $legacyNullable ? "?$nullable" : "$nullable|null";
        }

        $search = '/^class-string<mixed>$/';
        $replace = 'class-string';

        $types = explode('|', $type);
        // Leave invalid types alone
        $regex = count($types) > 1
            ? Regex::anchorAndDelimit(Regex::PHP_DNF_SEGMENT)
            : Regex::anchorAndDelimit('(' . Regex::PHP_TYPE . '|' . Regex::PHP_INTERSECTION_TYPE . ')');
        if (array_filter($types, fn(string $t) => !preg_match($regex, $t))) {
            return preg_replace($search, $replace, $type);
        }
        // Move `null` to the end of a union or DNF type
        $notNullTypes = array_filter($types, fn(string $t) => $t !== 'null');
        if ($types !== $notNullTypes) {
            $types = $notNullTypes;
            $types[] = 'null';
        }
        // Simplify composite types
        foreach ($types as &$type) {
            $brackets = false;
            if (($type[0] ?? null) === '(') {
                $brackets = true;
                $type = substr($type, 1, -1);
            }
            $_types = array_unique(explode('&', $type));
            $_types = preg_replace($search, $replace, $_types);
            $type = implode('&', $_types);
            if ($brackets && count($_types) > 1) {
                $type = "($type)";
            }
        }
        $types = array_unique($types);
        $types = preg_replace($search, $replace, $types);
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
