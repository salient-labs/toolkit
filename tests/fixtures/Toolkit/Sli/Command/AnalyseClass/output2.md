## `Salient\Tests\Sli\Command\AnalyseClass`

### Class `MyBaseClass`

<small>(27 lines, internal)</small>

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

##### Inherited from `MyInterface`

> ###### `public MY_SHORT_STRING = 'short'`
>
> ###### `public MY_ARRAY = ['Stringable' => 0]`
>
> Summary of MyInterface::MY_ARRAY

#### Properties

##### `public $MyVarProperty`

#### Methods

##### `final public static MyStaticMethod(static $instance): void`

<small>(4 lines)</small>

Summary of MyInterface::MyStaticMethod()

##### `protected MyOverriddenMethod(): int`

<small>(4 lines, no DocBlock)</small>

##### Inherited from `MyInterface`

> ###### `abstract public MyMethod(): mixed`
>
> Summary of MyInterface::MyMethod()

##### Inherited from `Stringable`

> ###### `abstract public __toString(): string`

### Class `MyClass`

<small>(57 lines)</small>

Summary of MyClass

```php
final class MyClass<T of int|string>
extends MyBaseClass
uses MyTrait
```

#### Constants

##### `protected float MY_FLOAT = 3.0`

##### Inherited from `MyBaseClass`

> ###### `protected MY_INT = 1`
>
> ###### `protected MY_LONG_STRING = <string>`
>
> ###### `public MY_LONGER_ARRAY = <array>`

##### Inherited from `MyInterface`

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

##### Inherited from `MyBaseClass`

> ###### `public $MyVarProperty`

##### Inherited from `MyTrait`

> ###### `private int $MyIntProperty = 2`

#### Methods

##### `__construct()`

<small>(4 lines, in API)</small>

```php
public function __construct(T $myProperty)
```

##### `MyTemplateMethod()`

<small>(4 lines)</small>

Summary of MyClass::MyTemplateMethod()

```php
protected function MyTemplateMethod<TInstance of MyInterface>(
    TInstance $instance,
    T|null $myProperty = null
): array<T,TInstance>
```

##### `__toString()`

<small>(4 lines, no DocBlock)</small>

```php
public function __toString(): string
```

##### Inherited from `MyBaseClass`

> ###### `final public static MyStaticMethod(static $instance): void`
>
> Summary of MyInterface::MyStaticMethod()

##### Inherited from `MyTrait`

> ###### `public MyMethod(): mixed`
>
> Summary of MyTrait::MyMethod()
>
> ###### `public MyOverriddenMethod(): int`
>
> Summary of MyTrait::MyOverriddenMethod()

### Interface `MyInterface`

<small>(25 lines, in API)</small>

Summary of MyInterface

#### Constants

##### `public MY_SHORT_STRING = 'short'`

##### `public MY_ARRAY = ['Stringable' => 0]`

Summary of MyInterface::MY_ARRAY

#### Methods

##### `public MyMethod(): mixed`

<small>(1 line)</small>

Summary of MyInterface::MyMethod()

##### `public static MyStaticMethod(static $instance): void`

<small>(1 line)</small>

Summary of MyInterface::MyStaticMethod()

### Trait `MyTrait`

<small>(19 lines, internal)</small>

Summary of MyTrait

#### Properties

##### `private int $MyIntProperty = 2`

#### Methods

##### `public MyMethod(): mixed`

<small>(1 line)</small>

Summary of MyTrait::MyMethod()

##### `public MyOverriddenMethod(): int`

<small>(4 lines)</small>

Summary of MyTrait::MyOverriddenMethod()

