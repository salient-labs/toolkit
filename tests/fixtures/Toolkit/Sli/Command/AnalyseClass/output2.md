## Salient\Tests\Sli\Command\AnalyseClass

### Classes

#### MyBaseClass

<small>(27 lines, internal)</small>

MyBaseClass

```php
abstract class MyBaseClass implements
    Salient\Tests\Sli\Command\AnalyseClass\MyInterface,
    Stringable
```

##### Constants

###### MY_INT

```php
protected const MY_INT = 1
```

###### MY_FLOAT

```php
protected const float MY_FLOAT = 1.0
```

###### MY_LONG_STRING

```php
protected const MY_LONG_STRING = 'string_with_more_than_20_characters'
```

###### MY_ARRAY

MyInterface::MY_ARRAY

```php
public const array<class-string,int> MY_ARRAY = [
    'Stringable' => 0,
    'JsonSerializable' => 1,
]
```

> ###### MY_SHORT_STRING
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyInterface::MY_SHORT_STRING`)</small>
> 
> ```php
> public const MY_SHORT_STRING = 'short'
> ```

##### Properties

###### MyVarProperty

```php
public $MyVarProperty
```

##### Methods

###### MyStaticMethod()

<small>(4 lines)</small>

MyInterface::MyStaticMethod()

```php
final public static function MyStaticMethod(static $instance): void
```

###### MyOverriddenMethod()

<small>(4 lines, no DocBlock)</small>

```php
protected function MyOverriddenMethod(): int
```

> ###### __toString()
> 
> <small>(from `Stringable::__toString()`)</small>
> 
> ```php
> abstract public function __toString(): string
> ```

> ###### MyMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyInterface::MyMethod()`)</small>
> 
> MyInterface::MyMethod()
> 
> ```php
> abstract public function MyMethod(): mixed
> ```

#### MyClass

<small>(42 lines)</small>

MyClass

```php
final class MyClass<T of int|string> extends
    Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass
```

##### Uses

- `Salient\Tests\Sli\Command\AnalyseClass\MyTrait`

##### Constants

###### MY_FLOAT

```php
protected const float MY_FLOAT = 3.0
```

> ###### MY_ARRAY
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass::MY_ARRAY`)</small>
> 
> MyInterface::MY_ARRAY
> 
> ```php
> public const array<class-string,int> MY_ARRAY = <array>
> ```

> ###### MY_INT
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass::MY_INT`)</small>
> 
> ```php
> protected const MY_INT = 1
> ```

> ###### MY_LONG_STRING
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass::MY_LONG_STRING`)</small>
> 
> ```php
> protected const MY_LONG_STRING = <string>
> ```

> ###### MY_SHORT_STRING
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyInterface::MY_SHORT_STRING`)</small>
> 
> ```php
> public const MY_SHORT_STRING = 'short'
> ```

##### Properties

###### MyProperty

MyClass::$MyProperty

```php
public T $MyProperty
```

> ###### MyIntProperty
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyTrait::MyIntProperty`)</small>
> 
> ```php
> private int $MyIntProperty = 2
> ```

> ###### MyVarProperty
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass::MyVarProperty`)</small>
> 
> ```php
> public $MyVarProperty
> ```

##### Methods

###### __construct()

<small>(4 lines, in API)</small>

```php
public function __construct(T $myProperty)
```

###### MyTemplateMethod()

<small>(4 lines)</small>

MyClass::MyTemplateMethod()

```php
protected function MyTemplateMethod<TInstance of MyInterface>(
    TInstance $instance,
    T|null $myProperty = null
): array<T,TInstance>
```

###### __toString()

<small>(4 lines, no DocBlock)</small>

```php
public function __toString(): string
```

> ###### MyMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyTrait::MyMethod()`)</small>
> 
> MyTrait::MyMethod()
> 
> ```php
> public function MyMethod(): mixed
> ```

> ###### MyOverriddenMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyTrait::MyOverriddenMethod()`)</small>
> 
> MyTrait::MyOverriddenMethod()
> 
> ```php
> public function MyOverriddenMethod(): int
> ```

> ###### MyStaticMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass::MyStaticMethod()`)</small>
> 
> MyInterface::MyStaticMethod()
> 
> ```php
> final public static function MyStaticMethod(static $instance): void
> ```

### Interfaces

#### MyInterface

<small>(27 lines, in API)</small>

MyInterface

```php
interface MyInterface
```

##### Constants

###### MY_SHORT_STRING

```php
public const MY_SHORT_STRING = 'short'
```

###### MY_ARRAY

MyInterface::MY_ARRAY

```php
public const array<class-string,int> MY_ARRAY = ['Stringable' => 0]
```

##### Methods

###### MyMethod()

<small>(1 line)</small>

MyInterface::MyMethod()

```php
public function MyMethod(): mixed
```

###### MyStaticMethod()

<small>(1 line)</small>

MyInterface::MyStaticMethod()

```php
public static function MyStaticMethod(static $instance): void
```

### Traits

#### MyTrait

<small>(19 lines, internal)</small>

MyTrait

```php
trait MyTrait
```

##### Properties

###### MyIntProperty

```php
private int $MyIntProperty = 2
```

##### Methods

###### MyMethod()

<small>(1 line)</small>

MyTrait::MyMethod()

```php
public function MyMethod(): mixed
```

###### MyOverriddenMethod()

<small>(4 lines)</small>

MyTrait::MyOverriddenMethod()

```php
public function MyOverriddenMethod(): int
```

