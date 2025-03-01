# salient/phpstan

> The PHPStan component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/phpstan` provides PHPStan extensions for the Salient toolkit.

For classes that use `ImmutableTrait` to return modified instances:

- `TypesAssignedByImmutableTraitRule` reports the following errors in calls to
  `with()` and `without()`:
  - `salient.property.notFound` for undefined properties
  - `salient.property.private` for inaccessible properties
  - `salient.property.type` for properties that do not accept the value applied
- `ImmutableTraitReadWritePropertiesExtension` tells PHPStan that properties
  visible to `with()` and `without()` are always read and written

## Documentation

[API documentation][api-docs] for `salient/phpstan` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.PHPStan.html
[toolkit]: https://github.com/salient-labs/toolkit
