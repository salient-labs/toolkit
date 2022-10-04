## Terminal output and logging

With a similar API to the [`console`][Console-API] object provided by web
browsers, [`Console`][Console][^1] provides:

- Familiar methods like `Console::log()` and `Console::error()`
- Variants like `Console::logOnce()` and `Console::errorOnce()` to output
  messages once per run
- Output to multiple targets
- Messages filtered by log level
- Formatting to reflect message priority and improve readability
- Colour output to TTYs

### Default targets

By default, all [`Console`][Console] messages are written to a file created with
mode 0600 at:

```php
sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
```

If PHP is running on the command line, errors and warnings are also written to
`STDERR`, informational messages are written to `STDOUT`, and if environment
variable `DEBUG` is non-empty, debug messages are also written to `STDOUT`.

To override these defaults, register at least one [`Console`][Console] output
target by calling [`Console::registerStdioTargets()`][registerStdio] or
[`Console::registerTarget()`][registerTarget] before any other `Console` methods
can be called, preferably while bootstrapping your application.

> [`AppContainer`][AppContainer] and [`CliAppContainer`][CliAppContainer] always
> call [`registerStdioTargets()`][registerStdio]. This registers the default
> `STDOUT` and `STDERR` targets explicitly and prevents creation of the
> temporary default output log. To create a log file that persists between
> reboots (in your project's `var/log` directory by default), call the app
> container's [`logConsoleMessages()`][logMessages] method.

### Output methods

| `Console` method | `ConsoleLevel`  | Message prefix | Default output target     |
| ---------------- | --------------- | -------------- | ------------------------- |
| `error[Once]()`  | `ERROR` = `3`   | ` !! `         | `STDERR`                  |
| `warn[Once]()`   | `WARNING` = `4` | `  ! `         | `STDERR`                  |
| `info[Once]()`   | `NOTICE` = `5`  | `==> `         | `STDOUT`                  |
| `log[Once]()`    | `INFO` = `6`    | ` -> `         | `STDOUT`                  |
| `debug[Once]()`  | `DEBUG` = `7`   | `--- `         | `STDOUT` (if `DEBUG` set) |
| `group()`[^2]    | `NOTICE` = `5`  | `>>> `         | `STDOUT`                  |
| `logProgress()`  | `INFO` = `6`    | ` -> `         | `STDOUT`                  |

[^1]: Actually a facade for [`ConsoleWriter`][ConsoleWriter].

[^2]: `Console::group()` adds a level of indentation to all `Console` output
    until `Console::groupEnd()` is called.


---

[AppContainer]: https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html
[CliAppContainer]: https://lkrms.github.io/php-util/classes/Lkrms-Container-CliAppContainer.html
[Console]: https://lkrms.github.io/php-util/classes/Lkrms-Facade-Console.html
[ConsoleWriter]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html
[logMessages]: https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html#method_logConsoleMessages
[registerStdio]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerStdioTargets
[registerTarget]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerTarget
[Console-API]: https://developer.mozilla.org/en-US/docs/Web/API/console

