<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

use JsonSerializable;
use Stringable;

/**
 * Summary of MyBaseClass
 *
 * @property $MyMagicProperty Description of MyBaseClass::$MyMagicProperty
 * @property-read int $MyMagicReadOnlyInt
 * @property-write mixed[] $MyMagicWriteOnlyArray
 *
 * @method static void MyStaticMagicMethod() Description of MyBaseClass::MyStaticMagicMethod()
 *
 * @internal
 */
abstract class MyBaseClass implements MyInterface, Stringable
{
    protected const MY_INT = 1;
    /** @var float */
    protected const MY_FLOAT = 1.0;
    protected const MY_LONG_STRING = 'string_with_more_than_20_characters';

    public const MY_LONGER_ARRAY = MyInterface::MY_ARRAY + [
        JsonSerializable::class => 1,
    ];

    // @phpstan-ignore missingType.property
    var $MyVarProperty;

    /**
     * @inheritDoc
     */
    final public static function MyStaticMethod(MyInterface $instance): void
    {
        $instance->MyMethod();
    }

    protected function MyOverriddenMethod(): int
    {
        return 1;
    }
}
