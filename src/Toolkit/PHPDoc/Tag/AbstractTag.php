<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use Salient\PHPDoc\PHPDocRegex;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Utility\Regex;
use Salient\Utility\Test;
use InvalidArgumentException;
use Stringable;

/**
 * @internal
 */
abstract class AbstractTag implements Immutable, Stringable
{
    use HasMutator {
        with as protected;
        without as protected;
    }

    protected string $Tag;
    protected string $Name;
    protected string $Type;
    protected ?string $Description;
    /** @var class-string|null */
    protected ?string $Class;
    protected ?string $Member;

    /**
     * @param class-string|null $class
     * @param array<string,class-string> $aliases
     */
    protected function __construct(
        string $tag,
        ?string $name = null,
        ?string $type = null,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        array $aliases = []
    ) {
        // Apply values least likely to be invalid--and most likely to be useful
        // in debug output--first
        $this->Class = $this->filterClass($class);
        $this->Member = $this->filterMember($member);
        $this->Tag = $this->filterTag($tag);
        if ($name !== null) {
            $this->Name = $this->filterString($name, 'name');
        }
        if ($type !== null) {
            $this->Type = $this->filterType($type, $aliases);
        }
        $this->Description = $this->filterString($description, 'description');
    }

    /**
     * Get the name of the tag
     */
    public function getTag(): string
    {
        return $this->Tag;
    }

    /**
     * Get the name of the entity associated with the tag
     */
    public function getName(): ?string
    {
        return $this->Name ?? null;
    }

    /**
     * Get the PHPDoc type of the entity associated with the tag
     */
    public function getType(): ?string
    {
        return $this->Type ?? null;
    }

    /**
     * Get the description of the tag
     */
    public function getDescription(): ?string
    {
        return $this->Description;
    }

    /**
     * Get the name of the class associated with the tag's PHPDoc
     *
     * @return class-string|null
     */
    public function getClass(): ?string
    {
        return $this->Class;
    }

    /**
     * Get the class member associated with the tag's PHPDoc
     */
    public function getMember(): ?string
    {
        return $this->Member;
    }

    /**
     * Get an instance with the given description
     *
     * @return static
     */
    public function withDescription(?string $description)
    {
        return $this->with(
            'Description',
            $this->filterString($description, 'description'),
        );
    }

    /**
     * Add missing values from an instance that represents the same entity in a
     * parent class or interface
     *
     * @param static $parent
     * @return static
     */
    public function inherit($parent)
    {
        return $this
            ->maybeInheritValue($parent, 'Type')
            ->maybeInheritValue($parent, 'Description');
    }

    /**
     * @param static $parent
     * @return static
     */
    final protected function maybeInheritValue($parent, string $property)
    {
        if (!isset($parent->$property)) {
            return $this;
        }

        if (!isset($this->$property)) {
            return $this->with($property, $parent->$property);
        }

        return $this;
    }

    final protected function filterTag(string $tag): string
    {
        if (!Regex::match(
            '/^' . PHPDocRegex::PHPDOC_TAG . '$/D',
            '@' . $tag,
        )) {
            $this->throw("Invalid tag '%s'", $tag);
        }
        return $tag;
    }

    /**
     * @template T of string|null
     *
     * @param T $class
     * @return T
     */
    final protected function filterClass(?string $class): ?string
    {
        if ($class !== null && !Test::isFqcn($class)) {
            $this->throw("Invalid class '%s'", $class);
        }
        return $class;
    }

    /**
     * @template T of string|null
     *
     * @param T $member
     * @return T
     */
    final protected function filterMember(?string $member): ?string
    {
        if ($member !== null && !Regex::match(
            '/^(\$?' . Regex::PHP_IDENTIFIER
                . '|' . Regex::PHP_IDENTIFIER . '(?:\(\))?)$/D',
            $member,
        )) {
            $this->throw("Invalid member '%s'", $member);
        }
        return $member;
    }

    /**
     * @template T of string|null
     *
     * @param T $type
     * @param array<string,class-string> $aliases
     * @return T
     */
    final protected function filterType(?string $type, array $aliases = []): ?string
    {
        if ($type === null) {
            // @phpstan-ignore return.type
            return null;
        }

        try {
            // @phpstan-ignore return.type
            return PHPDocUtil::normaliseType($type, $aliases, true);
        } catch (InvalidArgumentException $ex) {
            $this->throw('%s', $ex->getMessage());
        }
    }

    /**
     * @template T of string|null
     *
     * @param T $value
     * @return T
     */
    final protected function filterString(?string $value, string $name): ?string
    {
        if ($value !== null && trim($value) === '') {
            $this->throw("Invalid %s '%s'", $name, $value);
        }
        return $value;
    }

    /**
     * @param string|int|float ...$args
     * @return never
     */
    final protected function throw(string $message, ...$args): void
    {
        if (isset($this->Tag)) {
            $message .= ' for @%s';
            $args[] = $this->Tag;
        }

        $message .= ' in DocBlock';

        if (isset($this->Class)) {
            $message .= ' of %s';
            $args[] = $this->Class;
            if (isset($this->Member)) {
                $message .= '::%s';
                $args[] = $this->Member;
            }
        }

        throw new InvalidArgumentException(sprintf($message, ...$args));
    }

    /**
     * @return non-empty-string
     */
    abstract function __toString();
}
