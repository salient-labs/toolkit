# salient/testing

> The testing component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/testing` provides classes that are useful in test suites.

- `MockPhpStream` preserves data written to `php://` streams for subsequent
  reading via the same URI
- `MockTarget` records messages logged via the Salient toolkit's [Console
  API][salient/console]

## Documentation

[API documentation][api-docs] for `salient/testing` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.Testing.html
[salient/console]: https://packagist.org/packages/salient/console
[toolkit]: https://github.com/salient-labs/toolkit
