# lkrms/util

A lightweight PHP toolkit for elegant backend/CLI apps. Opinionated but not
presumptuous. PSR-friendly but not always compliant. *May contain traces of
Laravel.*

## Installation

Install the latest version with [Composer](https://getcomposer.org/):

```shell
composer require lkrms/util
```

## Documentation

[Online API documentation][api-docs] for `lkrms/util` tracks the `main` branch
of the [official GitHub repository][repo].

To generate your own API documentation, run [`phpdoc`][phpdoc] in the top-level
directory, then open `docs/api/index.html`.

Other documentation is available in this README and under [docs][docs].

## Using the CLI utility

`lkrms/util` includes code generators and other command-line tools you can use
during development by running `lk-util` from your project's `vendor/bin`
directory.

For a list of available subcommands, run `lk-util` with no arguments. For usage
information, use `help` as a subcommand or add the `--help` option.

### Environment variables

To make it easier to work with PHP namespaces on the command line, the following
defaults are taken from the environment:

| Variable             | Description                                         | Example                     |
| -------------------- | --------------------------------------------------- | --------------------------- |
| `DEFAULT_NAMESPACE`  | Applied to unqualified class names                  | `Lkrms\Tests\Sync\Entity`   |
| `BUILDER_NAMESPACE`  | Overrides `DEFAULT_NAMESPACE` for `Builder` classes | `Lkrms\Tests\Builder`       |
| `FACADE_NAMESPACE`   | Overrides `DEFAULT_NAMESPACE` for `Facade` classes  | `Lkrms\Tests\Facade`        |
| `PHPDOC_PACKAGE`     | Used if `--package` is not specified                | `Lkrms\Tests`               |
| `DEFAULT_PROVIDER`   | Used if `--provider` is not specified               | `JsonPlaceholderApi`        |
| `PROVIDER_NAMESPACE` | Applied to unqualified `--provider` class names     | `Lkrms\Tests\Sync\Provider` |


---

[api-docs]: https://lkrms.github.io/php-util/
[docs]: docs/
[phpdoc]: https://phpdoc.org/
[repo]: https://github.com/lkrms/php-util

