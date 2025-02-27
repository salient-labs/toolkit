<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Core\Rules;

use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;
use stdClass;

/**
 * @property-read bool $Bar
 */
class MyClassWithMutator implements Immutable
{
    use ImmutableTrait;

    /** @var array-key */
    private $Foo;
    private bool $Bar;

    /**
     * @param mixed $foo
     * @return static
     */
    public function withFoo($foo)
    {
        $mutant = $this->with('Foo', $foo);
        return $mutant->with('Bar', 0);
    }

    /**
     * @return static
     */
    public function withBar(bool $bar = true)
    {
        return $this->with('Bar', $bar);
    }

    /**
     * @return static
     */
    public function withoutBar()
    {
        return $this->without('Bar');
    }

    /**
     * @return static
     */
    public function withoutMultiple()
    {
        $mutant = $this->without('qux');
        $mutant = $mutant->without('bar');
        return $mutant->without('Foo');
    }
}

class MyClassWithMutatorAlias implements Immutable
{
    use ImmutableTrait {
        with as withPropertyValue;
    }

    private int $Foo;

    /**
     * @return static
     */
    public function withFoo(string $foo)
    {
        return $this->withPropertyValue('Foo', $foo);
    }

    /**
     * @return static
     */
    public function withFoo2(int $foo)
    {
        return $this->withPropertyValue('Foo', $foo);
    }
}

class MyDynamicClassWithMutator extends stdClass implements Immutable
{
    use ImmutableTrait;

    /**
     * @return static
     */
    public function withFoo(string $foo)
    {
        return $this->with('Foo', $foo);
    }
}

class MyClassWithProtectedMutator implements Immutable
{
    use ImmutableTrait {
        with as protected;
        without as protected;
    }
}

class MyClassWithInheritedMutator extends MyClassWithProtectedMutator
{
    private string $Foo;

    /**
     * @return static
     */
    public function withFoo(string $foo)
    {
        return $this->with('Foo', $foo);
    }

    /**
     * @return static
     */
    public function withoutFoo()
    {
        return $this->without('Foo');
    }
}
