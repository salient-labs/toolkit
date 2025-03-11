# Service containers

## `ContainerInterface`

If a service resolves to a new instance of a class that implements
`ContainerAwareInterface`, the container is passed to its
`ContainerAwareInterface::setContainer()` method.

Then, if the resolved instance implements `ServiceAwareInterface`, its
`ServiceAwareInterface::setService()` method is called.

A service provider registered via `ContainerInterface::provider()` or
`ContainerInterface::providers()` may also implement any combination of the
following interfaces:

- `SingletonInterface` to be instantiated once per container
- `HasServices` to specify which of its interfaces are services to register with
  the container
- `HasBindings` to bind additional services to the container
- `HasContextualBindings` to bind services to the container that only apply in
  the context of the provider

`SingletonInterface` is ignored if a lifetime other than `LIFETIME_INHERIT` is
given when the service provider is registered.

## `ApplicationInterface`

### `getCachePath()`

Appropriate for replaceable data that should persist between runs to improve
performance.

### `getDataPath()`

Appropriate for critical data that should persist indefinitely.

### `getTempPath()`

Appropriate for ephemeral data that shouldn't persist between runs.

## `Application`

### Runtime directories

Unless running from source or on Windows, [Application][Application] and
[CliApplication][CliApplication] follow the [XDG Base Directory
Specification][xdg-base-spec] when creating directories for storage of
application data.

| `Application` method | `.env` override   | Default (Phar, \*nix)             | Default (Phar, Windows)      | Default (running from source) |
| -------------------- | ----------------- | --------------------------------- | ---------------------------- | ----------------------------- |
| `getBasePath()`      | app_base_path[^1] |                                   |                              |                               |
| `getCachePath()`     | app_cache_path    | `$XDG_CACHE_HOME/<App>/cache`[^2] | `%LOCALAPPDATA%/<App>/cache` | `<BasePath>/var/cache`        |
| `getConfigPath()`    | app_config_path   | `$XDG_CONFIG_HOME/<App>`[^3]      | `%APPDATA%/<App>/config`     | `<BasePath>/var/lib/config`   |
| `getDataPath()`      | app_data_path     | `$XDG_DATA_HOME/<App>`[^4]        | `%APPDATA%/<App>/data`       | `<BasePath>/var/lib/data`     |
| `getLogPath()`       | app_log_path      | `$XDG_CACHE_HOME/<App>/log`       | `%LOCALAPPDATA%/<App>/log`   | `<BasePath>/var/log`          |
| `getTempPath()`      | app_temp_path     | `$XDG_CACHE_HOME/<App>/tmp`       | `%LOCALAPPDATA%/<App>/tmp`   | `<BasePath>/var/tmp`          |

[^1]:
    `app_base_path` is always ignored if `$basePath` is passed to
    [Application][Application]'s constructor.

[^2]:
    If `XDG_CACHE_HOME` is empty or not set, `$HOME/.cache` is used as the
    default

[^3]:
    If `XDG_CONFIG_HOME` is empty or not set, `$HOME/.config` is used as the
    default

[^4]:
    If `XDG_DATA_HOME` is empty or not set, `$HOME/.local/share` is used as the
    default

### `isRunningInProduction()`

Returns `true` if:

- the name of the current environment is `production`
- the application is running from a Phar archive, or
- the application was installed with `composer --no-dev`

[Application]:
  https://salient-labs.github.io/toolkit/Salient.Container.Application.html
[CliApplication]:
  https://salient-labs.github.io/toolkit/Salient.Cli.CliApplication.html
[xdg-base-spec]:
  https://specifications.freedesktop.org/basedir-spec/basedir-spec-latest.html
