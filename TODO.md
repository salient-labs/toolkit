# TODO

### General
- [ ] Review namespaces and classes before making a v1.0.0 release
  - [x] Add `abstract` to classes with exclusively `static` members
  - [x] `Assert`: `pregMatch()` -> `patternMatches()`
  - [ ] Refactor Curler
    - [ ] Remove/deprecate `...Json()` methods
    - [ ] Add support for parallel downloads and queued actions
  - [ ] `Runtime` -> `App`
  - [ ] Refactor Dice
  - [ ] Surface `Ioc` methods via `App`
- [ ] Implement a generic fluent interface (`TFluent`?) with instance creation and property getting/setting
- [ ] Throw custom exceptions
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [x] Adopt PHPStan
    - ~~Create [extensions](https://phpstan.org/developing-extensions/extension-types) as needed~~
  - [ ] Write more tests

### Documentation
- Add missing descriptions
  - [ ] `SyncOperation`
  - [x] `SyncOperationNotImplementedException`
- [ ] `SyncOperation`
  - [ ] Split "typically corresponds to" items

### Cache(/Trash)
- [ ] Remove/deprecate `isLoaded()` and `load()`
- [ ] Return `null` instead of `false` for missing entries

### Sync
- [x] Move `Sync\SyncProvider` and `Sync\SyncEntityProvider` to `Sync\Provider\`
  - ~~Create an alias at `Sync\SyncProvider`~~
- [ ] Implement automatic local storage of entities
  - [ ] When a `SyncEntity` is created, load a local instance before applying provider state
  - [ ] Add `static` methods like `getFrom($provider)` and `syncFrom($provider)` to `SyncEntity`
  - [ ] Generate and store deltas while applying provider state
  - [ ] Track "deltas in" and "deltas out" in local store?
- [x] Add optional callback and/or field map parameters to `TConstructible::from()` and `listFrom()`

### Cli
- [x] Implement `CliOptionType::ONE_OF_OPTIONAL`
- [x] Allow commands to be chained and/or invoked as functions
  - [x] Receive arguments via `CliCommand::__invoke()` instead of reading from `$GLOBALS["argv"]`
- [ ] Allow subcommands to be abbreviated
- [ ] Implement shared/default command options
- [ ] Add automatic `help` command

### Console
- [ ] Improve default targets so console messages aren't included in redirected output

### CLI utility
- [ ] `generate`:
  - [ ] Generate stubs from OpenAPI specs
- [ ] `http`:
  - [ ] Allow headers to be specified
