# salient/console

> The console component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/console` provides terminal output and message logging via an API
similar to `console` in web browsers.

- Familiar methods like [Console::log()][log] and [Console::error()][error]
- Variants like [Console::logOnce()][logOnce] and
  [Console::errorOnce()][errorOnce] to output messages once per run
- Output to multiple targets
- Messages filtered by log level
- Formatting that reflects message priority and improves readability
- Optional Markdown-like syntax for additional formatting
- Colour output to TTYs
- [PSR-3 (Logger Interface)][PSR-3] support via [Console::logger()][logger]

[error]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_error
[errorOnce]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_errorOnce
[log]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_log
[logger]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_logger
[logOnce]:
  https://salient-labs.github.io/toolkit/Salient.Core.Facade.Console.html#_logOnce
[PSR-3]: https://www.php-fig.org/psr/psr-3/

## Documentation

[API documentation][api-docs] for `salient/console` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.Console.html
[toolkit]: https://github.com/salient-labs/toolkit
