# salient/curler

> The Curler component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/curler` provides an HTTP client optimised for RESTful API endpoints.

- Simple, fluent interface for sending `HEAD`, `GET`, `POST`, `PUT`, `PATCH` and
  `DELETE` requests
- Flexible query string and payload handling
- Uses generators to iterate over data from endpoints that use pagination
- Response cache for `HEAD`, `GET` and optionally `POST` requests[^cache]
- Cookie handling and persistence
- Uses [PSR-7][] request, response and stream interfaces
- Implements [PSR-18 (HTTP Client)][PSR-18]
- Behaviour can be customised via stackable middleware
- Generates [HTTP Archive (HAR)][har] files for debugging and analysis

```php
<?php
$curler = new \Salient\Curler\Curler('https://api.github.com/repos/salient-labs/toolkit/releases/latest');
echo 'Latest release: ' . $curler->get()['tag_name'] . \PHP_EOL;
```

[^cache]: HTTP caching headers are ignored. USE RESPONSIBLY.

[har]: http://www.softwareishard.com/blog/har-12-spec/
[PSR-18]: https://www.php-fig.org/psr/psr-18/
[PSR-7]: https://www.php-fig.org/psr/psr-7/

## Documentation

[API documentation][api-docs] for `salient/curler` tracks the `main` branch of
the toolkit's [GitHub repository][toolkit], where further documentation can also
be found.

[api-docs]: https://salient-labs.github.io/toolkit/namespace-Salient.Curler.html
[toolkit]: https://github.com/salient-labs/toolkit
