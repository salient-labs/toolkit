# TODO

### General
- [ ] Review namespaces and classes before making a v1.0.0 release
  - [ ] Make classes `final` where possible
  - [ ] Replace instantiation via `new` or `DI` with `ContainerInterface->get` where possible
  - [ ] Adopt generators and iterators where possible
  - [ ] Refactor `Curler`
    - [ ] Remove/deprecate `...Json()` methods
    - [ ] Add support for parallel downloads
  - [x] `Cli` -> `CliAppContainer`, with `Cli` as a facade
    - [x] Reimplement `Cli::registerCommand()`
  - [x] `Util` -> `Utility`, with facades in `Facade`
  - [ ] Remove `Convert::arrayValuesToChildArray()` and `Convert::toNestedArrays()`
  - [ ] Finalise Dice refactor
  - ~~Adopt PHP's built-in `DateFormatter` alternative?~~
- [x] Implement a generic fluent interface (`Builder`) with instance creation ~~and property getting/setting~~
- [ ] Throw custom exceptions
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Write more tests

### Documentation
- [ ] Add missing descriptions

### Container
- [x] Make `singleton`, `bind` etc. fluent?
- [x] Initialise `Console` targets during `App::load`
- [x] Add `App::bindProviders(...$provider)` or similar (`bindServices`?)
- ~~Absorb `Err` into `App`~~
  - [x] Automatically create regular expressions for paths where non-critical errors should be suppressed

### Sync
- [x] Add `Provider` to `SyncEntity` and require it to be set during instantiation
- [ ] Implement lazy hydration
- [ ] Create one `SyncEntity` instance per provider per ID per run (to avoid recursion)
- [ ] Establish mechanism for resolving `$<entity>Id` properties to `$Entity` and vice-versa
- [ ] Implement automatic local storage of entities
  - [ ] When a `SyncEntity` is created, load a local instance before applying provider state
  - ~~Add `static` methods like `getFrom($provider)` and `syncFrom($provider)` to `SyncEntity`~~
  - [ ] Generate and store deltas while applying provider state
    - [ ] Retain audit log
    - [ ] Track "deltas in" and "deltas out"
  - [ ] Track foreign keys between backends
- [ ] Add `SyncException` as a base class
- [ ] Add `SyncEntityProvider::resolve()` and `fuzzyResolve()`

### Cli
- [x] Allow commands to be chained and/or invoked as functions
  - [x] Receive arguments via `CliCommand::__invoke()` instead of reading from `$GLOBALS["argv"]`
- [ ] Implement shared/default command options
- [ ] Add `ALL` as a `ONE_OF` option if `MultipleAllowed` is set
- [ ] Implement `CliOptionDataType`
  - [ ] `BOOLEAN` (flag)
  - [ ] `INTEGER` (flag + multiple allowed)
  - [ ] `STRING` (default otherwise)
  - [ ] `DATE`
  - [ ] `PATH` (must exist)
  - [ ] `FILE`, incl. stream support (must exist)
  - [ ] `DIRECTORY` (must exist)
  - [ ] `JSON`?
- [ ] Add `ENVIRONMENT VARIABLES` section to help
- [ ] Allow ad-hoc help sections
- [ ] Allow documentation-only nodes

### Console
- [x] Improve default targets so console messages aren't included in redirected output
- [ ] Validate `ConsoleLevel` values where necessary

### CLI utility
- [ ] `generate sync`:
  - [ ] Generate entities and providers from OpenAPI specs
- [ ] `generate facade`:
  - [x] Retrieve types from PHPDoc tags
  - [x] Use `__construct` parameters to generate `load` method
  - [ ] Detect return by reference
- [ ] `http`:
  - [ ] Allow headers to be specified
  - [ ] `--query` should handle duplicate fields
  - [ ] Share features with `generate sync entity` (and/or allow piping from one to the other)
