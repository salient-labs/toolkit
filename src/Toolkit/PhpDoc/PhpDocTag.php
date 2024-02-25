<?php declare(strict_types=1);

namespace Salient\PhpDoc;

use Salient\Core\Catalog\Regex;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;

/**
 * A tag extracted from a PHP DocBlock
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

    /**
     * @var class-string|null
     */
    public $Class;

    /**
     * @var string|null
     */
    public $Member;

    public function __construct(
        string $tag,
        ?string $name = null,
        ?string $type = null,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        bool $legacyNullable = false
    ) {
        $this->Tag = $tag;
        $this->Name = $name === null ? null : self::sanitiseString($name);
        $this->Type = $type === null ? null : self::normaliseType($type, $legacyNullable);
        $this->Description = $description === null ? null : self::sanitiseString($description);
        $this->Class = $class;
        $this->Member = $member;
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

        $pattern = [
            '/\bclass-string<(mixed|object)>/i',
        ];
        $replacement = [
            'class-string',
        ];
        $replace =
            function (array $types) use ($pattern, $replacement): array {
                /** @var string[] $types */
                return Pcre::replace($pattern, $replacement, $types);
            };

        if (!Pcre::match(
            Pcre::delimit('^' . Regex::PHPDOC_TYPE . '$', '/'),
            trim($type),
            $matches
        )) {
            return $replace([$type])[0];
        }

        $types = Str::splitAndTrimOutsideBrackets('|', $type);

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
        $phpTypeRegex = Pcre::delimit('^' . Regex::PHP_TYPE . '$', '/');
        foreach ($types as &$type) {
            $brackets = false;
            if ($type && $type[0] === '(' && $type[-1] === ')') {
                $brackets = true;
                $type = substr($type, 1, -1);
            }
            $type = implode('&', $_types = array_unique($replace(explode('&', $type))));
            if ($brackets && (count($_types) > 1 ||
                    !Pcre::match($phpTypeRegex, $type))) {
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
     */
    final public static function sanitiseString(?string $string): ?string
    {
        if ($string === null) {
            return null;
        }

        $string = rtrim($string);
        if ($string === '') {
            return null;
        }

        return $string;
    }
}
