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

> ##### `MY_ARRAY`
> 
> <small>(from `MyInterface`)</small>
> 
> Summary of MyInterface::MY_ARRAY
> 
> ```php
> public const array<class-string,int> MY_ARRAY = ['Stringable' => 0]
> ```

> ##### `MY_SHORT_STRING`
> 
> <small>(from `MyInterface`)</small>
> 
> ```php
> public const MY_SHORT_STRING = 'short'
> ```

#### Properties

##### `$MyVarProperty`

```php
public $MyVarProperty
```

#### Methods

##### `MyStaticMethod()`

Summary of MyInterface::MyStaticMethod()

```php
final public static function MyStaticMethod(static $instance): void
```

##### `MyOverriddenMethod()`

```php
protected function MyOverriddenMethod(): int
```

> ##### `__toString()`
> 
> <small>(from `Stringable`)</small>
> 
> ```php
> abstract public function __toString(): string
> ```

> ##### `MyMethod()`
> 
> <small>(from `MyInterface`)</small>
> 
> Summary of MyInterface::MyMethod()
> 
> ```php
> abstract public function MyMethod(): mixed
> ```

### Class `MyClass`

Summary of MyClass

```php
final class MyClass<T of int|string>
extends MyBaseClass
uses MyTrait
```

#### Constants

##### `MY_FLOAT`

```php
protected const float MY_FLOAT = 3.0
```

> ##### `MY_ARRAY`
> 
> <small>(from `MyInterface`)</small>
> 
> Summary of MyInterface::MY_ARRAY
> 
> ```php
> public const array<class-string,int> MY_ARRAY = ['Stringable' => 0]
> ```

> ##### `MY_INT`
> 
> <small>(from `MyBaseClass`)</small>
> 
> ```php
> protected const MY_INT = 1
> ```

> ##### `MY_LONG_STRING`
> 
> <small>(from `MyBaseClass`)</small>
> 
> ```php
> protected const MY_LONG_STRING = <string>
> ```

> ##### `MY_LONGER_ARRAY`
> 
> <small>(from `MyBaseClass`)</small>
> 
> ```php
> public const MY_LONGER_ARRAY = <array>
> ```

> ##### `MY_SHORT_STRING`
> 
> <small>(from `MyInterface`)</small>
> 
> ```php
> public const MY_SHORT_STRING = 'short'
> ```

#### Properties

##### `$MyProperty`

Summary of MyClass::$MyProperty

```php
public T $MyProperty
```

##### `$MyStaticProperty`

```php
private static $MyStaticProperty
```

##### `$MyStaticPropertyWithDefault`

```php
private static $MyStaticPropertyWithDefault = 0
```

##### `$MyStaticTypedProperty`

```php
private static int $MyStaticTypedProperty
```

##### `$MyNullableStaticTypedProperty`

```php
private static ?int $MyNullableStaticTypedProperty
```

##### `$MyNullableStaticTypedPropertyWithDefault`

```php
private static ?int $MyNullableStaticTypedPropertyWithDefault = null
```

> ##### `$MyIntProperty`
> 
> <small>(from `MyTrait`)</small>
> 
> ```php
> private int $MyIntProperty = 2
> ```

> ##### `$MyVarProperty`
> 
> <small>(from `MyBaseClass`)</small>
> 
> ```php
> public $MyVarProperty
> ```

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

> ##### `MyMethod()`
> 
> <small>(from `MyTrait`)</small>
> 
> Summary of MyTrait::MyMethod()
> 
> ```php
> public function MyMethod(): mixed
> ```

> ##### `MyOverriddenMethod()`
> 
> <small>(from `MyTrait`)</small>
> 
> Summary of MyTrait::MyOverriddenMethod()
> 
> ```php
> public function MyOverriddenMethod(): int
> ```

> ##### `MyStaticMethod()`
> 
> <small>(from `MyBaseClass`)</small>
> 
> Summary of MyInterface::MyStaticMethod()
> 
> ```php
> final public static function MyStaticMethod(static $instance): void
> ```

### Interface `MyInterface`

Summary of MyInterface

```php
interface MyInterface
```

#### Constants

##### `MY_SHORT_STRING`

```php
public const MY_SHORT_STRING = 'short'
```

##### `MY_ARRAY`

Summary of MyInterface::MY_ARRAY

```php
public const array<class-string,int> MY_ARRAY = ['Stringable' => 0]
```

#### Methods

##### `MyMethod()`

Summary of MyInterface::MyMethod()

```php
public function MyMethod(): mixed
```

##### `MyStaticMethod()`

Summary of MyInterface::MyStaticMethod()

```php
public static function MyStaticMethod(static $instance): void
```

### Trait `MyTrait`

Summary of MyTrait

```php
trait MyTrait
```

#### Properties

##### `$MyIntProperty`

```php
private int $MyIntProperty = 2
```

#### Methods

##### `MyMethod()`

Summary of MyTrait::MyMethod()

```php
public function MyMethod(): mixed
```

##### `MyOverriddenMethod()`

Summary of MyTrait::MyOverriddenMethod()

```php
public function MyOverriddenMethod(): int
```

