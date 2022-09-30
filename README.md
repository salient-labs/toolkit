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

To make it easier to work with PHP namespaces in a terminal, `lk-util` allows
default namespaces and classes to be specified via environment variables. The
following Visual Studio Code settings illustrate how they can be used:

```jsonc
{
  "settings": {
    "terminal.integrated.env.linux": {
      // Added to unqualified classes
      "DEFAULT_NAMESPACE": "Lkrms\\Tests\\Sync\\Entity",
      // Overrides DEFAULT_NAMESPACE when generating builder classes
      "BUILDER_NAMESPACE": "Lkrms\\Tests\\Builder",
      // Overrides DEFAULT_NAMESPACE when generating facade classes
      "FACADE_NAMESPACE": "Lkrms\\Tests\\Facade",
      // Used if '--package' is not specified
      "PHPDOC_PACKAGE": "Lkrms\\Tests",
      // Used if '--provider' is not specified
      "DEFAULT_PROVIDER": "JsonPlaceholderApi",
      // Added to unqualified '--provider' classes
      "PROVIDER_NAMESPACE": "Lkrms\\Tests\\Sync\\Provider"
    }
  }
}
```

## Using `Console` for terminal output and logging

To make it easier to create readable terminal output and log entries, the
[`Lkrms\Console\Console`][Console.php] class provides:

- Familiar methods like `Console::log()` and `Console::error()`
- Variants like `Console::logOnce()` and `Console::errorOnce()` to output
  messages once per run
- Output to an arbitrary number of registered targets
- Filtering of messages delivered to each target by log level
- Terminal-friendly message formatting

### Default targets

If a `Console` [output method](#output-methods) is called and no targets have
been registered via `Console::registerTarget()`, one or more targets are created
automatically to ensure messages are delivered or logged by default.

1. If PHP is running on the command line:
   - Warnings and errors are written to `STDERR`
   - If one, and only one, of `STDERR` and `STDOUT` is an interactive terminal,
     informational messages are also written to `STDERR`, otherwise they are
     written to `STDOUT`
   - If the `DEBUG` environment variable is set, debug messages are written to
     the same output stream as informational messages

   > This configuration can also be applied by calling:
   >
   > ```php
   > Console::registerOutputStreams();
   > ```

2. Warnings, errors, informational messages and debug messages are written to a
   temporary log file, readable only by the owner:
   ```
   {TMPDIR}/<basename>-<realpath_hash>-<user_id>.log
   ```

### Output methods

| `Console` method  | `ConsoleLevel`  | Message prefix | Default output target |
| ----------------- | --------------- | -------------- | --------------------- |
| `error[Once]()`   | `ERROR` = `3`   | ` !! `         | `STDERR`              |
| `warn[Once]()`    | `WARNING` = `4` | `  ! `         | `STDERR`              |
| `info[Once]()`    | `NOTICE` = `5`  | `==> `         | `STDOUT`              |
| `log[Once]()`     | `INFO` = `6`    | ` -> `         | `STDOUT`              |
| `debug[Once]()`   | `DEBUG` = `7`   | `--- `         | none                  |
| `group()`[^group] | `NOTICE` = `5`  | `>>> `         | `STDOUT`              |
| `logProgress()`   | `INFO` = `6`    | ` -> `         | `STDOUT`              |

[^group]: `Console::group()` adds a level of indentation to all `Console` output
    until `Console::groupEnd()` is called.

---

[api-docs]: https://lkrms.github.io/php-util/
[docs]: docs/
[phpdoc]: https://phpdoc.org/
[repo]: https://github.com/lkrms/php-util
[Console.php]: src/Console/Console.php
