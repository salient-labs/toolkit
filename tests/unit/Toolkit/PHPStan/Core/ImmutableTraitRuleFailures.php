<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Core;

use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;
use stdClass;

/**
 * @property-read bool $Bar
 */
class MyClassWithImmutable implements Immutable
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
        return $this
            ->with('Foo', $foo)
            ->with('Bar', 0);
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
        return $this
            ->without('qux')
            ->without('bar')
            ->without('Foo');
    }
}

class MyClassWithImmutableAlias implements Immutable
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

class MyDynamicClassWithImmutable extends stdClass implements Immutable
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

class MyClassWithProtectedImmutable implements Immutable
{
    use ImmutableTrait {
        with as protected;
        without as protected;
    }

    private string $Foo;
    private bool $Bar;
}

class MyClassWithInheritedImmutable extends MyClassWithProtectedImmutable
{
    private string $Foo;
    protected int $Bar;

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

    /**
     * @return static
     */
    public function withBar(int $bar)
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
}

class MyClassWithReusedImmutable extends MyClassWithProtectedImmutable
{
    use ImmutableTrait {
        with as protected;
        without as protected;
    }

    private string $Foo;
    protected int $Bar;

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
    public function withoutQux()
    {
        return $this->without('Qux');
    }
}

// trait MyImmutableTrait
// {
//     use ImmutableTrait;
// }
//
// class MyClassWithMyImmutable extends MyClassWithProtectedImmutable
// {
//     use MyImmutableTrait {
//         with as protected;
//         without as protected;
//     }
//
//     private string $Foo;
//     protected int $Bar;
//
//     /**
//      * @return static
//      */
//     public function withFoo(string $foo)
//     {
//         return $this->with('Foo', $foo);
//     }
//
//     /**
//      * @return static
//      */
//     public function withoutFoo()
//     {
//         return $this->without('Foo');
//     }
//
//     /**
//      * @return static
//      */
//     public function withBar(bool $bar = true)
//     {
//         return $this->with('Bar', $bar);
//     }
//
//     /**
//      * @return static
//      */
//     public function withoutQux()
//     {
//         return $this->without('Qux');
//     }
// }
