## Terminal output and logging

With a similar API to the `console` object provided by web browsers, the
[Console][Console] class[^1] provides:

- Familiar methods like `Console::log()` and `Console::error()`
- Variants like `Console::logOnce()` and `Console::errorOnce()` to output
  messages once per run
- Output to multiple targets
- Messages filtered by log level
- Formatting to reflect message priority and improve readability
- Colour output to TTYs

### Default targets

By default, all [Console][Console] messages are written to a file created with
mode 0600 at:

```php
sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
```

And when running on the command line:

- If `STDERR` is a TTY and `STDOUT` is not, [Console][Console] messages are
  written to `STDERR` to ensure `STDOUT` isn't tainted
- Otherwise, errors and warnings are written to `STDERR`, and informational
  messages are written to `STDOUT`
- Environment variable `CONSOLE_OUTPUT` may be set to `stderr` or `stdout` to
  override [Console][Console]'s default output stream(s)
- Debug messages are suppressed if environment variable `DEBUG` is unset or
  empty

To override these defaults, register at least one [Console][Console] output
target by calling [registerStdioTargets()][registerStdioTargets],
[registerStderrTarget()][registerStderrTarget], or
[registerTarget()][registerTarget] before any other `Console` methods can be
called, preferably while bootstrapping your application.

> [AppContainer][AppContainer] and [CliAppContainer][CliAppContainer] always
> call [registerStdioTargets()][registerStdioTargets]. This registers the
> default `STDOUT` and `STDERR` targets explicitly and prevents creation of the
> temporary default output log. To create a log file that persists between
> reboots (in your project's `var/log` directory by default), call the app
> container's [logConsoleMessages()][logConsoleMessages] method.

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

[^1]: Actually a facade for [ConsoleWriter][ConsoleWriter].

[^2]: `Console::group()` adds a level of indentation to all `Console` output
    until `Console::groupEnd()` is called.

### Formatting

The following Markdown-like syntax is supported in [Console][Console] messages:

| Style        | Tag                                                                         | Appearance                            | Example                                                                                              |
| ------------ | --------------------------------------------------------------------------- | ------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Heading      | `___` *text* `___`<br>`***` *text* `***`<br>`##` *text*                     | ***Bold + colour***                   | `___NAME___`<br>`***NAME***`<br>`## NAME` (closing hashes are optional)                              |
| "Bold"       | `__` *text* `__`<br>`**` *text* `**`                                        | **Bold**                              | `__command__`<br>`**command**`                                                                       |
| "Italic"     | `_` *text* `_`<br>`*` *text* `*`                                            | *Secondary colour*                    | `_argument_`<br>`*argument*`                                                                         |
| "Underline"  | `<` *text* `>`                                                              | *<u>Secondary colour + underline</u>* | `<argument>`                                                                                         |
| Low priority | `~~` *text* `~~`                                                            | ~~Dim~~                               | `~~/path/to/script.php:42~~`                                                                         |
| Preformatted | `` ` `` *preformatted text* `` ` ``<br>` ``` ` *preformatted block* ` ``` ` | **`Bold`**<br>`Unchanged`             | `` `<inline-code>` `` (bold applied as above)<br><pre>\`\`\`&#10;&lt;code-block&gt;&#10;\`\`\`</pre> |


[AppContainer]: https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html
[CliAppContainer]: https://lkrms.github.io/php-util/classes/Lkrms-Cli-CliAppContainer.html
[Console]: https://lkrms.github.io/php-util/classes/Lkrms-Facade-Console.html
[ConsoleWriter]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html
[logConsoleMessages]: https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html#method_logConsoleMessages
[registerStderrTarget]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerStderrTarget
[registerStdioTargets]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerStdioTargets
[registerTarget]: https://lkrms.github.io/php-util/classes/Lkrms-Console-ConsoleWriter.html#method_registerTarget

