# TODO

### General
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Adopt PHPStan, incl. creating [extensions](https://phpstan.org/developing-extensions/extension-types)
  - [ ] Write more tests
- [ ] Adopt camelCase method names
  - [x] `Assert`
  - [ ] `Cache`
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
  - [ ] `Err\Err`
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
  - [ ] `Trash`

### Cli
- [x] Implement `CliOptionType::ONE_OF_OPTIONAL`
- [ ] Allow commands to be chained and/or invoked as functions
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

