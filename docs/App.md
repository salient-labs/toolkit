## Working with application service containers

### Runtime directories

Unless running from source or on Windows, [Application][Application] and
[CliApplication][CliApplication] follow the [XDG Base Directory
Specification][xdg-base-spec] when creating directories for storage of
application data.

| `Application` method | `.env` override   | Default (Phar, *nix)              | Default (Phar, Windows)      | Default (running from source) |
| -------------------- | ----------------- | --------------------------------- | ---------------------------- | ----------------------------- |
| `getBasePath()`      | app_base_path[^1] |                                   |                              |                               |
| `getCachePath()`     | app_cache_path    | `$XDG_CACHE_HOME/<App>/cache`[^2] | `%LOCALAPPDATA%/<App>/cache` | `<BasePath>/var/cache`        |
| `getConfigPath()`    | app_config_path   | `$XDG_CONFIG_HOME/<App>`[^3]      | `%APPDATA%/<App>/config`     | `<BasePath>/config`           |
| `getDataPath()`      | app_data_path     | `$XDG_DATA_HOME/<App>`[^4]        | `%APPDATA%/<App>/data`       | `<BasePath>/var/lib`          |
| `getLogPath()`       | app_log_path      | `$XDG_CACHE_HOME/<App>/log`       | `%LOCALAPPDATA%/<App>/log`   | `<BasePath>/var/log`          |
| `getTempPath()`      | app_temp_path     | `$XDG_CACHE_HOME/<App>/tmp`       | `%LOCALAPPDATA%/<App>/tmp`   | `<BasePath>/var/tmp`          |

[^1]: `app_base_path` is always ignored if `$basePath` is passed to
    [Application][Application]'s constructor.

[^2]: If `XDG_CACHE_HOME` is empty or not set, `$HOME/.cache` is used as the
    default

[^3]: If `XDG_CONFIG_HOME` is empty or not set, `$HOME/.config` is used as the
    default

[^4]: If `XDG_DATA_HOME` is empty or not set, `$HOME/.local/share` is used as
    the default


[Application]: https://lkrms.github.io/php-util/Salient.Container.Application.html
[CliApplication]: https://lkrms.github.io/php-util/Salient.Cli.CliApplication.html
[xdg-base-spec]: https://specifications.freedesktop.org/basedir-spec/basedir-spec-latest.html

