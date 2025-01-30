# salient/utils

> The utilities component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/utils` provides utility methods via the stateless classes below.

> If your project uses `File::find()` or `File::pruneDir()`,
> [salient/iterators][] must also be installed.

- **`Arr`** works with arrays and other iterables.
- **`Date`** works with date and time values, timezones and intervals.
- **`Debug`** gets data from the call stack.
- **`Env`** manipulates environment variables, loads values from `.env` files,
  and applies values from the environment to the script.
- **`File`** works with files, streams and paths, and provides filesystem
  function wrappers that throw an exception on failure.
- **`Format`** makes data human-readable.
- **`Get`** extracts, converts and generates data. For example:
  - `Get::coalesce()` is similar to the SQL `COALESCE()` function
  - `Get::code()` improves upon `var_export()`
  - `Get::copy()` gets a deep copy of an object
  - `Get::eol()` gets a string's end-of-line sequence
  - `Get::uuid()` generates or converts a UUID
- **`Inflect`** converts English words to different forms, e.g. from singular to
  plural.
- **`Json`** provides JSON function wrappers that throw an exception on failure.
- **`Package`** retrieves information from Composer's runtime API, e.g. the name
  of the root package.
- **`Reflect`** works with PHP's reflection API.
- **`Regex`** provides `preg_*()` function wrappers that throw an exception on
  failure.
- **`Str`** manipulates strings. For example:
  - `Str::expandLeadingTabs()` expands leading tabs to spaces
  - `Str::matchCase()` matches the case of one string to another
  - `Str::ngrams()` gets a string's n-grams
  - `Str::snake()` converts a string to snake_case
  - `Str::splitDelimited()` safely splits strings that contain delimiters
- **`Sys`** retrieves information about the runtime environment, and provides a
  handler for exit signals (`SIGTERM`, `SIGINT` and `SIGHUP`).
- **`Test`** performs tests on values.

[salient/iterators]: https://github.com/salient-labs/toolkit-iterators

## Documentation

[API documentation][api-docs] for `salient/utils` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.Utility.html
[toolkit]: https://github.com/salient-labs/toolkit
