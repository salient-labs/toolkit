# salient/utils

> The utils component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/utils` provides a suite of useful utility methods via stateless
classes.

- **`Arr`** works with arrays and iterables.

- **`Date`** works with date and time values, timezones and intervals.

- **`Debug`** gets caller information by normalising backtrace data.

- **`Env`** retrieves environment variables, loads values from `.env` files, and
  applies values from the environment to the script.

- **`File`** provides methods for filesystem operations that throw exceptions on
  failure.

- **`Format`** makes data human-readable.

- **`Get`** extracts, converts and generates data. For example:

  - `Get::coalesce()` replicates the SQL `COALESCE()` function
  - `Get::code()` improves upon `var_export()`
  - `Get::copy()` gets a deep copy of an object
  - `Get::eol()` gets a string's end-of-line sequence
  - `Get::uuid()` generates or converts a UUID

- **`Inflect`** converts English words to different forms, e.g. from singular to
  plural.

- **`Json`** provides methods for encoding and decoding JSON data that throw
  exceptions on failure.

- **`Package`** retrieves information from Composer's runtime API, e.g. the name
  of the root package.

- **`Reflect`** works with PHP's reflection API.

- **`Regex`** provides methods for working with regular expressions that throw
  exceptions on failure.

- **`Str`** manipulates strings. For example:

  - `Str::expandLeadingTabs()` expands leading tabs to spaces
  - `Str::matchCase()` matches the case of one string to another
  - `Str::ngrams()` gets a string's n-grams
  - `Str::splitDelimited()` safely splits strings that contain delimiters
  - `Str::toSnakeCase()` converts a string to snake_case

- **`Sys`** retrieves information about the runtime environment, and provides a
  handler for exit signals (`SIGTERM`, `SIGINT` and `SIGHUP`).

- **`Test`** performs tests on values.

## Documentation

[API documentation][api-docs] for `salient/utils` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.Utility.html
[toolkit]: https://github.com/salient-labs/toolkit
