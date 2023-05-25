## Salient\Tests\Sli\Command\AnalyseClass

### Classes

#### MyBaseClass

<small>(15 lines, internal)</small>

MyBaseClass

```php
abstract class MyBaseClass implements
    Salient\Tests\Sli\Command\AnalyseClass\MyInterface,
    Stringable
```

##### Methods

###### MyStaticMethod()

<small>(4 lines)</small>

MyInterface::MyStaticMethod()

```php
final public static MyStaticMethod(static $instance): void
```

###### MyOverriddenMethod()

<small>(4 lines, no DocBlock)</small>

```php
protected MyOverriddenMethod(): int
```

> ###### __toString()
> 
> <small>(from `Stringable::__toString()`)</small>
> 
> ```php
> abstract public __toString(): string
> ```

> ###### MyMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyInterface::MyMethod()`)</small>
> 
> MyInterface::MyMethod()
> 
> ```php
> abstract public MyMethod(): mixed
> ```

#### MyClass

<small>(40 lines)</small>

MyClass

```php
final class MyClass<T of int|string> extends
    Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass
```

##### Methods

###### __construct()

<small>(4 lines, in API)</small>

```php
public __construct(T $myProperty)
```

###### MyTemplateMethod()

<small>(4 lines)</small>

MyClass::MyTemplateMethod()

```php
protected MyTemplateMethod<TInstance of MyInterface>(
    TInstance $instance,
    T|null $myProperty = null
): array<T,TInstance>
```

###### __toString()

<small>(4 lines, no DocBlock)</small>

```php
public __toString(): string
```

> ###### MyMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyTrait::MyMethod()`)</small>
> 
> MyTrait::MyMethod()
> 
> ```php
> public MyMethod(): mixed
> ```

> ###### MyOverriddenMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyTrait::MyOverriddenMethod()`)</small>
> 
> MyTrait::MyOverriddenMethod()
> 
> ```php
> public MyOverriddenMethod(): int
> ```

> ###### MyStaticMethod()
> 
> <small>(from `Salient\Tests\Sli\Command\AnalyseClass\MyBaseClass::MyStaticMethod()`)</small>
> 
> MyInterface::MyStaticMethod()
> 
> ```php
> final public static MyStaticMethod(static $instance): void
> ```

### Interfaces

#### MyInterface

<small>(16 lines, in API)</small>

MyInterface

```php
interface MyInterface
```

##### Methods

###### MyMethod()

<small>(1 line)</small>

MyInterface::MyMethod()

```php
public MyMethod(): mixed
```

###### MyStaticMethod()

<small>(1 line)</small>

MyInterface::MyStaticMethod()

```php
public static MyStaticMethod(static $instance): void
```

### Traits

#### MyTrait

<small>(17 lines, internal)</small>

MyTrait

```php
trait MyTrait
```

##### Methods

###### MyMethod()

<small>(1 line)</small>

MyTrait::MyMethod()

```php
public MyMethod(): mixed
```

###### MyOverriddenMethod()

<small>(4 lines)</small>

MyTrait::MyOverriddenMethod()

```php
public MyOverriddenMethod(): int
```

