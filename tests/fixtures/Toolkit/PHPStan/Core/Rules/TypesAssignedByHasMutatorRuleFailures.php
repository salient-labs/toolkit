<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Core\Rules;

use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use stdClass;

/**
 * @property-read bool $Bar
 */
class MyClassWithMutator implements Immutable
{
    use HasMutator;

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
    use HasMutator {
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
    use HasMutator;

    /**
     * @return static
     */
    public function withFoo(string $foo)
    {
        return $this->with('Foo', $foo);
    }
}
