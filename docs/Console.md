## Terminal output and logging

With a similar API to the `console` object provided by web browsers, the
[Console][] class[^1] provides:

- Familiar methods like `Console::log()` and `Console::error()`
- Variants like `Console::logOnce()` and `Console::errorOnce()` to output
  messages once per run
- Output to multiple targets
- Messages filtered by log level
- Formatting to reflect message priority and improve readability
- Colour output to TTYs

### Default targets

By default, [Console][] output is appended to a file in the default temporary
directory, created with mode `0600` if it doesn't already exist:

```php
<?php
sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
```

If the value of environment variable `console_target` is `stderr` or `stdout`,
[Console][] output is also written to `STDERR` or `STDOUT` respectively.

If `console_target` is not set and the script is running on the command line:

- If `STDERR` is a TTY and `STDOUT` is not, console messages are written to
  `STDERR` so output to `STDOUT` isn't tainted
- Otherwise, errors and warnings are written to `STDERR`, and informational
  messages are written to `STDOUT`

Debug messages are written to the output log but are not written to `STDOUT` or
`STDERR` if environment variable `DEBUG` is empty or not set.

To override these defaults, register at least one [Console][] output target,
e.g. by calling [registerTarget()][], before any other `Console` methods are
called, preferably while bootstrapping your application.

> [Application][] and [CliApplication][] always call [registerStderrTarget()][],
> which registers the default `STDOUT` and `STDERR` targets and prevents
> creation of the default output log. To create a log file that persists between
> reboots (in your project's `var/log` directory by default), call the app
> container's [logOutput()][] method.

### Output methods

<!-- prettier-ignore -->
| Method          | Message level         | Default prefix   | Default output target     |
| --------------- | --------------------- | ---------------- | ------------------------- |
| `error[Once]()` | `LEVEL_ERROR` = `3`   | ` !  `           | `STDERR`                  |
| `warn[Once]()`  | `LEVEL_WARNING` = `4` | ` ^  `           | `STDERR`                  |
| `info[Once]()`  | `LEVEL_NOTICE` = `5`  | ` ➤  `           | `STDOUT`                  |
| `log[Once]()`   | `LEVEL_INFO` = `6`    | ` -  `           | `STDOUT`                  |
| `debug[Once]()` | `LEVEL_DEBUG` = `7`   | ` :  `           | `STDOUT` (if `DEBUG` set) |
| `group()`[^2]   | `LEVEL_NOTICE` = `5`  | ` »  `           | `STDOUT`                  |
| `logProgress()` | `LEVEL_INFO` = `6`    | ` ⠋  ` (spinner) | `STDOUT`                  |

[^1]:
    Actually a facade for [ConsoleInterface][], which is backed by a shared
    instance of [Console][ConsoleService] by default.

[^2]:
    `Console::group()` adds a level of indentation to all `Console` output until
    `Console::groupEnd()` is called.

### Formatting

The following Markdown-like syntax is supported in [Console][] messages:

| Style        | Tag                                                    | Typical appearance                    | Example                                                                   |
| ------------ | ------------------------------------------------------ | ------------------------------------- | ------------------------------------------------------------------------- |
| Heading      | `___` text `___`<br>`***` text `***`<br>`##` text `##` | **_Bold + primary colour_**           | `___NAME___`<br>`***NAME***`<br>`## NAME` (closing delimiter is optional) |
| "Bold"       | `__` text `__`<br>`**` text `**`                       | **Bold + default colour**             | `__command__`<br>`**command**`                                            |
| "Italic"     | `_` text `_`<br>`*` text `*`                           | _Secondary colour_                    | `_argument_`<br>`*argument*`                                              |
| "Underline"  | `<` text `>`                                           | _<u>Secondary colour + underline</u>_ | `<argument>`                                                              |
| Low priority | `~~` text `~~`                                         | <small>Dim</small>                    | `~~/path/to/script.php:42~~`                                              |
| Inline code  | `` ` `` text `` ` ``                                   | <code>Bold</code>                     | `` The input format can be specified using the `-f/--from` option. ``     |
| Code block   | ` ``` `<br>text<br>` ``` `                             | <pre><code>Unchanged</code></pre>     | <pre><code>\`\`\`&#10;$baz = Foo::bar();&#10;\`\`\`</code></pre>          |

[Application]:
  https://salient-labs.github.io/toolkit/Salient.Container.Application.html
[CliApplication]:
  https://salient-labs.github.io/toolkit/Salient.Cli.CliApplication.html
[Console]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html
[ConsoleInterface]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Console.ConsoleInterface.html
[ConsoleService]:
  https://salient-labs.github.io/toolkit/Salient.Console.Console.html
[logOutput()]:
  https://salient-labs.github.io/toolkit/Salient.Container.Application.html#_logOutput
[registerStderrTarget()]:
  https://salient-labs.github.io/toolkit/Salient.Console.Console.html#_registerStderrTarget
[registerTarget()]:
  https://salient-labs.github.io/toolkit/Salient.Console.Console.html#_registerTarget
