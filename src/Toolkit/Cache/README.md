# salient/cache

> The caching component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/cache` provides a key-value store backed by a SQLite database.

- Implements [PSR-16 (Common Interface for Caching Libraries)][PSR-16]
- Multiple cache operations can be grouped into an atomic transaction via a
  time-bound instance of the cache[^1] that maintains an exclusive lock on the
  underlying database until it goes out of scope or is explicitly closed

## Documentation

[API documentation][api-docs] for `salient/cache` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[^1]: See [CacheStore::asOfNow()][asOfNow] for more information.

[api-docs]: https://salient-labs.github.io/toolkit/namespace-Salient.Cache.html
[asOfNow]:
  https://salient-labs.github.io/toolkit/Salient.Cache.CacheStore.html#_asOfNow
[PSR-16]: https://www.php-fig.org/psr/psr-16/
[toolkit]: https://github.com/salient-labs/toolkit
