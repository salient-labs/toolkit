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

##### `public $MyVarProperty`

#### Methods

##### `final public static MyStaticMethod(static $instance): void`

Summary of MyInterface::MyStaticMethod()

##### `protected MyOverriddenMethod(): int`

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

##### `public T $MyProperty`

Summary of MyClass::$MyProperty

##### `private static $MyStaticProperty`

##### `private static $MyStaticPropertyWithDefault = 0`

##### `private static int $MyStaticTypedProperty`

##### `private static ?int $MyNullableStaticTypedProperty`

##### `private static ?int $MyNullableStaticTypedPropertyWithDefault = null`

> ###### `public $MyVarProperty`
>
> ###### `private int $MyIntProperty = 2`

#### Methods

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

#### Methods

##### `public MyMethod(): mixed`

Summary of MyInterface::MyMethod()

##### `public static MyStaticMethod(static $instance): void`

Summary of MyInterface::MyStaticMethod()

### Trait `MyTrait`

Summary of MyTrait

#### Properties

##### `private int $MyIntProperty = 2`

#### Methods

##### `public MyMethod(): mixed`

Summary of MyTrait::MyMethod()

##### `public MyOverriddenMethod(): int`

Summary of MyTrait::MyOverriddenMethod()

