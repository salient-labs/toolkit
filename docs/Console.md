# Console

The Console component provides terminal output and message logging via an API
similar to `console` in web browsers.

- Familiar methods like [Console::log()][log()] and [Console::error()][error()]
- Variants like [Console::logOnce()][logOnce()] and
  [Console::errorOnce()][errorOnce()] to output messages once per run
- Output to multiple targets
- Messages filtered by log level
- Formatting that reflects message priority and improves readability
- Optional Markdown-like syntax for additional formatting
- Colour output to TTYs
- [PSR-3 (Logger Interface)][PSR-3] support via [Console::logger()][logger()]

## Default targets

If no targets are registered to receive [Console][] output when a message is
written:

1. Output is appended to a log file in PHP's default temporary directory,
   created with mode `0600` if it doesn't already exist:

   ```php
   sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
   ```

2. If the script is running on the command line, output is also written to
   `STDERR`.

Debug messages are suppressed unless debug mode is enabled in the environment,
e.g. by setting `DEBUG=1`.

To override these defaults:

- register at least one [Console][] output target while bootstrapping your
  application, or
- create an [Application][] or [CliApplication][] and optionally use
  [logOutput()][] to write output a persistent log file

## Message methods

<!-- prettier-ignore -->
| Method                             | Message level         | Default prefix             |
| ---------------------------------- | --------------------- | -------------------------- |
| `error()`, `errorOnce()`           | `LEVEL_ERROR` = `3`   | ` !  `                     |
| `warn()`, `warnOnce()`             | `LEVEL_WARNING` = `4` | ` ^  `                     |
| `group()`,`groupEnd()`             | `LEVEL_NOTICE` = `5`  | ` »  `, ` «  `             |
| `info()`, `infoOnce()`             | `LEVEL_NOTICE` = `5`  | ` ➤  `                     |
| `log()`, `logOnce()`               | `LEVEL_INFO` = `6`    | ` -  `                     |
| `logProgress()`, `clearProgress()` | `LEVEL_INFO` = `6`    | ` ⠋  ` (spinner, TTY only) |
| `debug()`, `debugOnce()`           | `LEVEL_DEBUG` = `7`   | ` :  `                     |

## Formatting

The following Markdown-like syntax is supported in [Console][] messages:

| Style        | Tag                                                    | Typical appearance                    | Example                                                                   |
| ------------ | ------------------------------------------------------ | ------------------------------------- | ------------------------------------------------------------------------- |
| Heading      | `___` text `___`<br>`***` text `***`<br>`##` text `##` | **_Bold + primary colour_**           | `___NAME___`<br>`***NAME***`<br>`## NAME` (closing delimiter is optional) |
| "Bold"       | `__` text `__`<br>`**` text `**`                       | **Bold + default colour**             | `__command__`<br>`**command**`                                            |
| "Italic"     | `_` text `_`<br>`*` text `*`                           | _Secondary colour_                    | `_argument_`<br>`*argument*`                                              |
| "Underline"  | `<` text `>`                                           | _<u>Secondary colour + underline</u>_ | `<argument>`                                                              |
| Low priority | `~~` text `~~`                                         | <small>Faint</small>                  | `~~/path/to/script.php:42~~`                                              |
| Inline code  | `` ` `` text `` ` ``                                   | <code>Bold</code>                     | `` The input format can be specified using the `-f/--from` option. ``     |
| Code block   | ` ``` `<br>text<br>` ``` `                             | <pre><code>Unchanged</code></pre>     | <pre><code>\`\`\`&#10;$baz = Foo::bar();&#10;\`\`\`</code></pre>          |

Paragraphs outside code blocks are wrapped to the width reported by the target,
and backslash-escaped punctuation characters and line breaks are preserved.

Escaped line breaks may have a leading space, so the following are equivalent:

```
Text with a \
hard line break.

Text with a\
hard line break.
```

[Application]:
  https://salient-labs.github.io/toolkit/Salient.Container.Application.html
[CliApplication]:
  https://salient-labs.github.io/toolkit/Salient.Cli.CliApplication.html
[Console]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html
[error()]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_error
[errorOnce()]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_errorOnce
[log()]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_log
[logger()]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_logger
[logOnce()]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_logOnce
[logOutput()]:
  https://salient-labs.github.io/toolkit/Salient.Container.Application.html#_logOutput
[PSR-3]: https://www.php-fig.org/psr/psr-3/
