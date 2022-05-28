# TODO

### General
- [ ] Review namespaces and classes before making a v1.0.0 release
  - Make classes `final` where possible
  - Replace `abstract` with `extends \Core\Utility` where appropriate
  - [ ] Complete class review
    - [ ] `Core\Mixin\TExtensible`
    - [ ] `Core\Mixin\TGettable`
    - [ ] `Core\Mixin\TResolvable`
    - [ ] `Core\Mixin\TSettable`
    - [x] `Core\Contract\IClassCache`
    - [x] `Core\Contract\IConstructible`
    - [x] `Core\Contract\IConstructibleByProvider`
    - [x] `Core\Contract\IExtensible`
    - [x] `Core\Contract\IGettable`
    - [x] `Core\Contract\IProvider`
    - [x] `Core\Contract\IResolvable`
    - [x] `Core\Contract\ISettable`
    - [x] `Core\Contract\ISingular`
    - [x] `Core\Mixin\TClassCache`
    - [x] `Core\Mixin\TConstructible`
    - [x] `Core\Mixin\TConstructibleByProvider`
    - [x] `Core\Mixin\TFullyGettable`
    - [x] `Core\Mixin\TFullySettable`
  - [x] `Assert`: `pregMatch()` -> `patternMatches()`
  - [ ] Refactor Curler
    - [ ] Remove/deprecate `...Json()` methods
    - [ ] Add support for parallel downloads and queued actions
  - [ ] `Runtime` -> `App`
  - [ ] Refactor Dice
  - [x] Surface `Ioc` methods via `App`
  - [x] Move `ClosureBuilder` to `Core\Support`
- [ ] Implement a generic fluent interface (`TFluent`?) with instance creation and property getting/setting
- [ ] Throw custom exceptions
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Write more tests

### Documentation
- [ ] Add missing descriptions

### Cache(/Trash)
- [x] Remove/deprecate `isLoaded()` and `load()`
- ~~Return `null` instead of `false` for missing entries~~

### Sync
- [x] Add `Provider` to `SyncEntity` and require it to be set during instantiation
- [ ] Establish mechanism for resolving `$<entity>Id` properties to `$Entity` and vice-versa
- [ ] Implement automatic local storage of entities
  - [ ] When a `SyncEntity` is created, load a local instance before applying provider state
  - [ ] Add `static` methods like `getFrom($provider)` and `syncFrom($provider)` to `SyncEntity`
  - [ ] Generate and store deltas while applying provider state
  - [ ] Track "deltas in" and "deltas out" in local store?
- [ ] Add `SyncException` as a base class

### Cli
- [x] Allow commands to be chained and/or invoked as functions
  - [x] Receive arguments via `CliCommand::__invoke()` instead of reading from `$GLOBALS["argv"]`
- [x] Allow subcommands to be abbreviated
- [ ] Implement shared/default command options
- [x] Add automatic `help` command
- [ ] Add `ALL` as a `ONE_OF` option if `MultipleAllowed` is set

### Console
- [ ] Improve default targets so console messages aren't included in redirected output

### CLI utility
- [ ] `generate`:
  - [ ] Generate stubs from OpenAPI specs
- [ ] `http`:
  - [ ] Allow headers to be specified
