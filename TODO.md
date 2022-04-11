# TODO

### General
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Adopt PHPStan, incl. creating [extensions](https://phpstan.org/developing-extensions/extension-types)
  - [ ] Write more tests
- [x] Review `Template` classes for `PropertyResolver` candidates
  - [x] Move `PropertyResolver` to a more appropriate namespace?
- [x] Adopt camelCase method names
  - [x] `Console`
  - [x] `Console\Console`
  - [x] `Console\ConsoleColour`
  - [x] `Console\ConsoleLevel`
  - [x] `Console\ConsoleTarget`
  - [x] `Console\ConsoleTarget\Analog`
  - [x] `Console\ConsoleTarget\Logger`
  - [x] `Console\ConsoleTarget\Stream`
  - [x] `Curler`
  - [x] `Curler\CachingCurler`
  - [x] `Curler\Curler`
  - [x] `Curler\CurlerException`
  - [x] `Curler\CurlerFile`
  - [x] `Curler\CurlerHeaders`

### Sync

- [ ] Move `Sync\SyncProvider` to `Sync\Provider\SyncProvider`
  - [ ] Create an alias at `Sync\SyncProvider`
- [ ] Implement automatic local storage of entities
- [ ] Add optional callback and/or field map parameters to `TConstructible::from()` and `listFrom()`

### Cli
- [x] Implement `CliOptionType::ONE_OF_OPTIONAL`
- [ ] Allow commands to be chained and/or invoked as functions
  - [x] Receive arguments via `CliCommand::__invoke()` instead of reading from `$GLOBALS["argv"]`
- [ ] Allow subcommands to be abbreviated
- [ ] Implement shared/default command options
- [ ] Add automatic `help` command

### Console
- [ ] Improve default targets so console messages aren't included in redirected output

### CLI utility
- [ ] `generate`:
  - [x] Add `sync-entity-provider` option to make `get` method parameter nullable
  - [ ] Generate stubs from OpenAPI specs
- [ ] `http`:
  - [x] Use same option names and environment variables as `generate` commands

