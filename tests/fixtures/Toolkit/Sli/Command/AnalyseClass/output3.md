## `Salient\Tests\Sli\Command\AnalyseClass`

### Class `MyBaseClass`

Summary of MyBaseClass

```php
abstract class MyBaseClass
implements MyInterface, Stringable
```

#### Constants

##### `MY_INT`

```php
protected const MY_INT = 1
```

##### `MY_FLOAT`

```php
protected const float MY_FLOAT = 1.0
```

##### `MY_LONG_STRING`

```php
protected const MY_LONG_STRING = 'string_with_more_than_20_characters'
```

##### `MY_LONGER_ARRAY`

```php
public const MY_LONGER_ARRAY = [
    'Stringable' => 0,
    'JsonSerializable' => 1,
]
```

> ###### `public MY_SHORT_STRING = 'short'`
>
> ###### `public MY_ARRAY = ['Stringable' => 0]`
>
> Summary of MyInterface::MY_ARRAY

#### Properties

##### `public $MyMagicProperty`

Description of MyBaseClass::$MyMagicProperty

##### `public readonly int $MyMagicReadOnlyInt`

##### `public mixed[] $MyMagicWriteOnlyArray` (write-only)

##### `public $MyVarProperty`

> ###### `public readonly int $MyMagicInterfaceProperty`

#### Methods

##### `public static MyStaticMagicMethod(): void`

Description of MyBaseClass::MyStaticMagicMethod()

##### `final public static MyStaticMethod(static $instance): void`

Summary of MyInterface::MyStaticMethod()

##### `protected MyOverriddenMethod(): int`

> ###### `public MyMagicInterfaceMethod(): mixed`
>
> ###### `abstract public MyMethod(): mixed`
>
> Summary of MyInterface::MyMethod()
>
> ###### `abstract public __toString(): string`

### Class `MyClass`

Summary of MyClass

```php
final class MyClass<T of int|string>
extends MyBaseClass
uses MyTrait
```

#### Constants

##### `protected float MY_FLOAT = 3.0`

> ###### `protected MY_INT = 1`
>
> ###### `protected MY_LONG_STRING = <string>`
>
> ###### `public MY_LONGER_ARRAY = <array>`
>
> ###### `public MY_SHORT_STRING = 'short'`
>
> ###### `public MY_ARRAY = ['Stringable' => 0]`
>
> Summary of MyInterface::MY_ARRAY

#### Properties

##### `public int $MyMagicProperty`

Description of MyBaseClass::$MyMagicProperty

##### `public T $MyProperty`

Summary of MyClass::$MyProperty

##### `private static $MyStaticProperty`

##### `private static $MyStaticPropertyWithDefault = 0`

##### `private static int $MyStaticTypedProperty`

##### `private static ?int $MyNullableStaticTypedProperty`

##### `private static ?int $MyNullableStaticTypedPropertyWithDefault = null`

> ###### `public readonly int $MyMagicReadOnlyInt`
>
> ###### `public mixed[] $MyMagicWriteOnlyArray` (write-only)
>
> ###### `public $MyVarProperty`
>
> ###### `public readonly int $MyMagicInterfaceProperty`
>
> ###### `public mixed[] $MyMagicTraitProperty` (write-only)
>
> ###### `private int $MyIntProperty = 2`

#### Methods

##### `MyMagicMethod()`

```php
public function MyMagicMethod(string $name, mixed ...$values): int
```

##### `MyStaticMagicMethod()`

Description of MyBaseClass::MyStaticMagicMethod()

```php
public static function MyStaticMagicMethod(int $id = null): void
```

##### `__construct()`

```php
public function __construct(T $myProperty)
```

##### `MyTemplateMethod()`

Summary of MyClass::MyTemplateMethod()

```php
protected function MyTemplateMethod<TInstance of MyInterface>(
    TInstance $instance,
    T|null $myProperty = null
): array<T,TInstance>
```

##### `__toString()`

```php
public function __toString(): string
```

> ###### `final public static MyStaticMethod(static $instance): void`
>
> Summary of MyInterface::MyStaticMethod()
>
> ###### `public MyMagicInterfaceMethod(): mixed`
>
> ###### `public MyMagicTraitMethod(): string`
>
> ###### `public MyMethod(): mixed`
>
> Summary of MyTrait::MyMethod()
>
> ###### `public MyOverriddenMethod(): int`
>
> Summary of MyTrait::MyOverriddenMethod()

### Interface `MyInterface`

Summary of MyInterface

#### Constants

##### `public MY_SHORT_STRING = 'short'`

##### `public MY_ARRAY = ['Stringable' => 0]`

Summary of MyInterface::MY_ARRAY

#### Properties

##### `public readonly int $MyMagicInterfaceProperty`

#### Methods

##### `public MyMagicInterfaceMethod(): mixed`

##### `public MyMethod(): mixed`

Summary of MyInterface::MyMethod()

##### `public static MyStaticMethod(static $instance): void`

Summary of MyInterface::MyStaticMethod()

### Trait `MyTrait`

Summary of MyTrait

#### Properties

##### `public mixed[] $MyMagicTraitProperty` (write-only)

##### `private int $MyIntProperty = 2`

#### Methods

##### `public MyMagicTraitMethod(): string`

##### `public MyMethod(): mixed`

Summary of MyTrait::MyMethod()

##### `public MyOverriddenMethod(): int`

Summary of MyTrait::MyOverriddenMethod()

