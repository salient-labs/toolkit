<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

enum MyBackedEnum: int implements MyInterface
{
    use MyBackedEnumTrait;

    case Foo = 0;
    case Bar = 1;
    case Baz = 2;
}
