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

By default, [Console][Console] output is appended to a file in the default
temporary directory, created with mode 0600 if it doesn't already exist:

```php
sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
```

If the value of environment variable `CONSOLE_OUTPUT` is `stderr` or `stdout`,
[Console][Console] output is also written to `STDERR` or `STDOUT` respectively.

If `CONSOLE_OUTPUT` is not set and the script is running on the command line:

- If `STDERR` is a TTY and `STDOUT` is not, console messages are written to
  `STDERR` so output to `STDOUT` isn't tainted
- Otherwise, errors and warnings are written to `STDERR`, and informational
  messages are written to `STDOUT`

Debug messages are written to the output log but are not written to `STDOUT` or
`STDERR` if environment variable `DEBUG` is empty or not set.

To override these defaults, register at least one [Console][Console] output
target, e.g. by calling [registerTarget()][registerTarget], before any other
`Console` methods are called, preferably while bootstrapping your application.

> [Application][Application] and [CliApplication][CliApplication] always call
> [registerStdioTargets()][registerStdioTargets], which registers the default
> `STDOUT` and `STDERR` targets and prevents creation of the default output log.
> To create a log file that persists between reboots (in your project's
> `var/log` directory by default), call the app container's
> [logConsoleMessages()][logConsoleMessages] method.

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

| Style        | Tag                                                    | Typical appearance                    | Example                                                                   |
| ------------ | ------------------------------------------------------ | ------------------------------------- | ------------------------------------------------------------------------- |
| Heading      | `___` text `___`<br>`***` text `***`<br>`##` text `##` | ***Bold + primary colour***           | `___NAME___`<br>`***NAME***`<br>`## NAME` (closing delimiter is optional) |
| "Bold"       | `__` text `__`<br>`**` text `**`                       | **Bold + default colour**             | `__command__`<br>`**command**`                                            |
| "Italic"     | `_` text `_`<br>`*` text `*`                           | *Secondary colour*                    | `_argument_`<br>`*argument*`                                              |
| "Underline"  | `<` text `>`                                           | *<u>Secondary colour + underline</u>* | `<argument>`                                                              |
| Low priority | `~~` text `~~`                                         | <small>Dim</small>                    | `~~/path/to/script.php:42~~`                                              |
| Inline code  | `` ` `` text `` ` ``                                   | <code>Bold</code>                     | `` The input format can be specified using the `-f/--from` option. ``     |
| Code block   | ` ``` `<br>text<br>` ``` `                             | <pre><code>Unchanged</code></pre>     | <pre><code>\`\`\`&#10;$baz = Foo::bar();&#10;\`\`\`</code></pre>          |


[Application]: https://lkrms.github.io/php-util/Lkrms.Container.Application.html
[CliApplication]: https://lkrms.github.io/php-util/Lkrms.Cli.CliApplication.html
[Console]: https://lkrms.github.io/php-util/Lkrms.Facade.Console.html
[ConsoleWriter]: https://lkrms.github.io/php-util/Lkrms.Console.ConsoleWriter.html
[logConsoleMessages]: https://lkrms.github.io/php-util/Lkrms.Container.Application.html#_logConsoleMessages
[registerStdioTargets]: https://lkrms.github.io/php-util/Lkrms.Console.ConsoleWriter.html#_registerStdioTargets
[registerTarget]: https://lkrms.github.io/php-util/Lkrms.Console.ConsoleWriter.html#_registerTarget

