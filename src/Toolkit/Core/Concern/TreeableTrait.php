<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Entity\Treeable;
use Salient\Core\Reflection\ClassReflection;
use Salient\Utility\Exception\ShouldNotHappenException;
use InvalidArgumentException;

/**
 * @api
 *
 * @phpstan-require-implements Treeable
 */
trait TreeableTrait
{
    /** @var array<class-string<self>,array{string,string}> */
    private static array $TreeableProperties;

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getParent()
    {
        [$_parent] = self::$TreeableProperties[static::class]
            ?? self::getTreeableProperties();
        return $this->{$_parent} ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getChildren(): array
    {
        [, $_children] = self::$TreeableProperties[static::class]
            ?? self::getTreeableProperties();
        return $this->{$_children} ?? [];
    }

    /**
     * @inheritDoc
     */
    public function setParent($parent)
    {
        [$_parent, $_children] = self::$TreeableProperties[static::class]
            ?? self::getTreeableProperties();

        if ($parent === ($this->{$_parent} ?? null) && (
            $parent === null
            || in_array($this, $parent->{$_children} ?? [], true)
        )) {
            return $this;
        }

        if (isset($this->{$_parent})) {
            $this->{$_parent}->{$_children} = array_values(array_filter(
                $this->{$_parent}->{$_children} ?? [],
                fn($child) => $child !== $this,
            ));
        }

        $this->{$_parent} = $parent;
        if ($parent !== null) {
            $parent->{$_children}[] = $this;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addChild($child)
    {
        return $child->setParent($this);
    }

    /**
     * @inheritDoc
     */
    public function removeChild($child)
    {
        if ($child->getParent() !== $this) {
            throw new InvalidArgumentException('Invalid child');
        }
        return $child->setParent(null);
    }

    /**
     * @inheritDoc
     */
    public function getDepth(): int
    {
        [$_parent] = self::$TreeableProperties[static::class]
            ?? self::getTreeableProperties();
        $depth = 0;
        $parent = $this;
        while ($parent = $parent->{$_parent}) {
            $depth++;
        }
        return $depth;
    }

    /**
     * @inheritDoc
     */
    public function countDescendants(): int
    {
        [, $_children] = self::$TreeableProperties[static::class]
            ?? self::getTreeableProperties();
        return $this->doCountDescendants($_children);
    }

    private function doCountDescendants(string $_children): int
    {
        /** @var static[] */
        $children = $this->{$_children} ?? [];
        if (!$children) {
            return 0;
        }
        $count = 0;
        foreach ($children as $child) {
            $count += 1 + $child->doCountDescendants($_children);
        }
        return $count;
    }

    /**
     * @return array{string,string}
     */
    private static function getTreeableProperties(): array
    {
        $class = new ClassReflection(static::class);
        if ($class->isTreeable()) {
            return self::$TreeableProperties[static::class] = [
                $class->getParentProperty(),
                $class->getChildrenProperty(),
            ];
        }

        // @codeCoverageIgnoreStart
        throw new ShouldNotHappenException(sprintf(
            '%s does not implement %s',
            static::class,
            Treeable::class,
        ));
        // @codeCoverageIgnoreEnd
    }
}
