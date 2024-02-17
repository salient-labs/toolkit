<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

class ClassWithRefs
{
    public int $Foo = 0;

    public string $Bar = '';

    /**
     * @var mixed[]
     */
    public array $Baz = [];

    public ?object $Qux = null;

    public int $FooByVal;

    public string $BarByVal;

    /**
     * @var mixed[]
     */
    public array $BazByVal;

    public object $QuxByVal;

    /**
     * @var RefClass[]
     */
    public array $Refs;

    public function bind(): void
    {
        $this->Refs = [];
        $this->Refs[] = new RefClass($this->Foo);
        $this->Refs[] = new RefClass($this->Bar);
        $this->Refs[] = new RefClass($this->Baz);
        $this->Refs[] = new RefClass($this->Qux);
    }

    public function unbind(): void
    {
        $this->Refs = [];
    }

    /**
     * @param mixed[] $baz
     */
    public function apply(int $foo, string $bar, array $baz, object $qux): void
    {
        $this->Refs[0]->apply($foo);
        $this->Refs[1]->apply($bar);
        $this->Refs[2]->apply($baz);
        $this->Refs[3]->apply($qux);
        $this->FooByVal = $foo;
        $this->BarByVal = $bar;
        $this->BazByVal = $baz;
        $this->QuxByVal = $qux;
    }
}
