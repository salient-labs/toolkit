# lkrms/util

A PHP toolkit with opinionated defaults and a small footprint.

## Installation

Install the latest version with [Composer](https://getcomposer.org/):

```shell
composer require lkrms/util
```

## Using the CLI utility

`lkrms/util` includes code generators and other command-line tools you can use
during development by running `lk-util` from your project's `vendor/bin`
directory.

For a list of available subcommands, run `lk-util` with no arguments. For usage
information, add `--help` to any command.

### Environment variables

To make it easier to work with fully-qualified class names in a terminal,
`lk-util` allows fallback namespaces and classes to be specified via environment
variables. The following Visual Studio Code settings illustrate how they can be
used:

```jsonc
{
  "settings": {
    "terminal.integrated.env.linux": {
      // Added to unqualified '--class' names
      "SYNC_ENTITY_NAMESPACE": "Lkrms\\Tests\\Sync\\Entity",
      // Used if '--package' is not specified
      "SYNC_ENTITY_PACKAGE": "Lkrms\\Tests",
      // Used if '--provider' is not specified
      "SYNC_ENTITY_PROVIDER": "JsonPlaceholderApi",
      // Added to unqualified '--provider' names (e.g. "JsonPlaceholderApi")
      "SYNC_PROVIDER_NAMESPACE": "Lkrms\\Tests\\Sync\\Provider"
    }
  }
}
```

## Using `Console` for terminal output and logging

> You don't have to use `Lkrms\Console` for message logging, but `lkrms/util`
> uses it internally, so becoming familiar with its default behaviour is
> recommended.

To make it easier to create readable terminal output and log entries, the
[`Lkrms\Console\Console`][Console.php] class provides:

- Familiar methods like `Console::log()` and `Console::error()`
- Variants like `Console::logOnce()` and `Console::errorOnce()` to output
  messages once per run
- Output to an arbitrary number of registered targets
- Filtering of messages delivered to each target by log level
- Terminal-friendly message formatting

### Default targets

If no output targets[^targets] are registered via `Console::addTarget()` and PHP
is running on the command line:

- Warnings and errors are written to `STDERR`
- Informational messages are written to `STDOUT`
- Debug messages are suppressed

Similarly, if no log targets are registered:

- A temporary log file based on the name of the running script is created at:
  ```
  {sys_get_temp_dir()}/<basename>-<realpath_hash>-<user_id>.log
  ```
- Warnings, errors, informational messages and debug messages are written to the
  log file

This can be disabled by calling `Console::disableDefaultLogTarget()` while
bootstrapping your app.

### Output methods

| `Console` method  | `ConsoleLevel`  | Message prefix | Default output target |
| ----------------- | --------------- | -------------- | --------------------- |
| `error[Once]()`   | `ERROR` = `3`   | ` !! `         | `STDERR`              |
| `warn[Once]()`    | `WARNING` = `4` | `  ! `         | `STDERR`              |
| `group()`[^group] | `NOTICE` = `5`  | `>>> `         | `STDOUT`              |
| `info[Once]()`    | `NOTICE` = `5`  | `==> `         | `STDOUT`              |
| `log[Once]()`     | `INFO` = `6`    | ` -> `         | `STDOUT`              |
| `logProgress()`   | `INFO` = `6`    | ` -> `         | `STDOUT`              |
| `debug[Once]()`   | `DEBUG` = `7`   | `--- `         | none                  |

[^group]: `Console::group()` adds a level of indentation to all `Console` output
    until `Console::groupEnd()` is called.

---

[^targets]: `$target` is regarded as an output target if `$target->isStdout()`
    or `$target->isStderr()` return `true`.

[Console.php]: src/Console/Console.php
