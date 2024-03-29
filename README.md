# salient/toolkit

> A lightweight PHP toolkit for expressive backend/CLI apps. Opinionated but
> adaptable. Negligible dependencies. May contain traces of Laravel.

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

## Installation

Install the latest version with [Composer](https://getcomposer.org/):

```shell
composer require salient/toolkit
```

## Documentation

API documentation for `salient/toolkit` is [available online][api-docs]. It
tracks the `main` branch of the project's [GitHub repository][repo] and is
generated by [ApiGen][].

You can build the API documentation locally by running the following commands in
the top-level directory. It should appear in `docs/api` after a few seconds.

```shell
composer -d tools/apigen install
```

```shell
tools/apigen/vendor/bin/apigen -c tools/apigen/apigen.neon
```

Other documentation is available [here][docs] and in the source code.

[api-docs]: https://salient-labs.github.io/toolkit/
[ApiGen]: https://github.com/ApiGen/ApiGen
[docs]: docs/
[repo]: https://github.com/salient-labs/toolkit
