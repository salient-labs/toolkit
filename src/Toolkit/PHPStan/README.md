# salient/phpstan

> The PHPStan component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/phpstan` provides PHPStan extensions for development with the Salient
toolkit.

## Extensions

### Dynamic return type

| Extension                             | Method                   |
| ------------------------------------- | ------------------------ |
| `ArrExtendReturnTypeExtension`        | [Arr::extend()][]        |
| `ArrFlattenReturnTypeExtension`       | [Arr::flatten()][]       |
| `ArrWhereNotEmptyReturnTypeExtension` | [Arr::whereNotEmpty()][] |
| `ArrWhereNotNullReturnTypeExtension`  | [Arr::whereNotNull()][]  |
| `GetCoalesceReturnTypeExtension`      | [Get::coalesce()][]      |
| `StrCoalesceReturnTypeExtension`      | [Str::coalesce()][]      |

### Custom rule

| Extension            | Description                                                         | Error identifiers                                                                    |
| -------------------- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `ContainerRule`      | Checks calls to [ContainerInterface][] methods                      | `salient.service.type`                                                               |
| `GetCoalesceRule`    | Checks for unnecessary use of [Get::coalesce()][]                   | `salient.needless.coalesce`                                                          |
| `ImmutableTraitRule` | Checks calls to [ImmutableTrait][] methods `with()` and `without()` | `salient.property.notFound`<br>`salient.property.private`<br>`salient.property.type` |

### Always-read and written properties

| Extension                                    | Description                                                                  |
| -------------------------------------------- | ---------------------------------------------------------------------------- |
| `ImmutableTraitReadWritePropertiesExtension` | Properties visible to [ImmutableTrait][] methods are always read and written |

## Documentation

[API documentation][api-docs] for `salient/phpstan` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.PHPStan.html
[Arr::extend()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Arr.html#_extend
[Arr::flatten()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Arr.html#_flatten
[Arr::whereNotEmpty()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Arr.html#_whereNotEmpty
[Arr::whereNotNull()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Arr.html#_whereNotNull
[ContainerInterface]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Container.ContainerInterface.html
[Get::coalesce()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Get.html#_coalesce
[ImmutableTrait]:
  https://salient-labs.github.io/toolkit/Salient.Core.Concern.ImmutableTrait.html
[Str::coalesce()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Str.html#_coalesce
[toolkit]: https://github.com/salient-labs/toolkit
