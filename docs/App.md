## Working with application service containers

### Runtime directories

Unless running from source or on Windows, [App][App] and [Cli][Cli] (facades for
[AppContainer][AppContainer] and [CliAppContainer][CliAppContainer]
respectively) follow the [XDG Base Directory Specification][xdg-base-spec]:

| `AppContainer` path | `.env` override   | Default (Phar, *nix)              | Default (Phar, Windows)      | Default (source)       |
| ------------------- | ----------------- | --------------------------------- | ---------------------------- | ---------------------- |
| `BasePath`          | app_base_path[^1] |                                   |                              |                        |
| `CachePath`         | app_cache_path    | `$XDG_CACHE_HOME/<App>/cache`[^2] | `%LOCALAPPDATA%/<App>/cache` | `<BasePath>/var/cache` |
| `ConfigPath`        | app_config_path   | `$XDG_CONFIG_HOME/<App>`[^3]      | `%APPDATA%/<App>/config`     | `<BasePath>/config`    |
| `DataPath`          | app_data_path     | `$XDG_DATA_HOME/<App>`[^4]        | `%APPDATA%/<App>/data`       | `<BasePath>/var/lib`   |
| `LogPath`           | app_log_path      | `$XDG_CACHE_HOME/<App>/log`       | `%LOCALAPPDATA%/<App>/log`   | `<BasePath>/var/log`   |
| `TempPath`          | app_temp_path     | `$XDG_CACHE_HOME/<App>/tmp`       | `%LOCALAPPDATA%/<App>/tmp`   | `<BasePath>/var/tmp`   |

[^1]: `app_base_path` is always ignored if `$basePath` is passed to
    [AppContainer][AppContainer]'s constructor.

[^2]: If `XDG_CACHE_HOME` is empty or not set, `$HOME/.cache` is used as the
    default

[^3]: If `XDG_CONFIG_HOME` is empty or not set, `$HOME/.config` is used as the
    default

[^4]: If `XDG_DATA_HOME` is empty or not set, `$HOME/.local/share` is used as
    the default


[App]: https://lkrms.github.io/php-util/classes/Lkrms-Facade-App.html
[Cli]: https://lkrms.github.io/php-util/classes/Lkrms-Facade-Cli.html
[AppContainer]: https://lkrms.github.io/php-util/classes/Lkrms-Container-AppContainer.html
[CliAppContainer]: https://lkrms.github.io/php-util/classes/Lkrms-Cli-CliAppContainer.html
[xdg-base-spec]: https://specifications.freedesktop.org/basedir-spec/basedir-spec-latest.html

