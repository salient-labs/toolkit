# TODO

### General
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Adopt PHPStan, incl. creating [extensions](https://phpstan.org/developing-extensions/extension-types)
  - [ ] Write more tests
- [ ] Review `Template` classes (and others) for `TClassCache` and `PropertyResolver` candidates
  - [ ] Move `PropertyResolver` to a more appropriate namespace?
- [ ] Adopt camelCase method names
  - [x] `Assert`
  - [x] `Cache`
  - [x] `Cli`
  - [x] `Cli\Cli`
  - [x] `Cli\CliCommand`
  - [x] `Cli\CliInvalidArgumentException`
  - [x] `Cli\CliOption`
  - [x] `Cli\CliOptionType`
  - [x] `Console`
  - [ ] `Console\Console`
  - [x] `Console\ConsoleColour`
  - [ ] `Console\ConsoleLevel`
  - [ ] `Console\ConsoleTarget`
  - [ ] `Console\ConsoleTarget\Analog`
  - [ ] `Console\ConsoleTarget\Logger`
  - [ ] `Console\ConsoleTarget\Stream`
  - [x] `Convert`
  - [x] `Curler`
  - [ ] `Curler\CachingCurler`
  - [ ] `Curler\Curler`
  - [x] `Curler\CurlerException`
  - [ ] `Curler\CurlerFile`
  - [ ] `Curler\CurlerHeaders`
  - [x] `Env`
  - [x] `Err`
  - [x] `Err\CliHandler`
  - [x] `Err\Err`
  - [x] `Error`
  - [x] `File`
  - [x] `Format`
  - [x] `Generate`
  - [x] `Ioc\Ioc`
  - [x] `Reflect`
  - [x] `Store\Sqlite`
  - [x] `Sync\SyncEntity`
  - [x] `Sync\SyncEntityFuzzyResolver`
  - [x] `Sync\SyncEntityProvider`
  - [x] `Sync\SyncEntityResolver`
  - [x] `Sync\SyncOperation`
  - [x] `Sync\SyncProvider`
  - [x] `Template\IAccessible`
  - [x] `Template\IExtensible`
  - [x] `Template\IGettable`
  - [x] `Template\IResolvable`
  - [x] `Template\ISettable`
  - [x] `Template\PropertyResolver`
  - [x] `Template\Singleton`
  - [x] `Template\TConstructible`
  - [x] `Template\TExtensible`
  - [x] `Template\TGettable`
  - [x] `Template\TSettable`
  - [x] `Template\TSingleton`
  - [x] `Test`
  - [x] `Trash`

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

