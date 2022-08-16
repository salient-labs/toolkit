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
  - [ ] `Util` -> `Utility`, with facades in `Facade`
  - [ ] Reimplement `Convert::arrayValuesToChildArray()` and `Convert::toNestedArrays()` as a closure builder
  - [ ] Refactor Dice
  - ~~Adopt PHP's built-in `DateFormatter` alternative?~~
- [ ] Implement a generic fluent interface (`TFluent`?) with instance creation and property getting/setting
- [ ] Throw custom exceptions
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Write more tests

### Documentation
- [ ] Add missing descriptions

### Container
- [x] Make `singleton`, `bind` etc. fluent?
- [ ] Initialise `Console` targets during `App::load`
- [ ] Add `App::bindProviders(...$provider)` or similar (`bindServices`?)
- ~~Absorb `Err` into `App`~~
  - [x] Automatically create regular expressions for paths where non-critical errors should be suppressed

### Sync
- [x] Add `Provider` to `SyncEntity` and require it to be set during instantiation
- [ ] Implement lazy hydration
- [ ] Create one `SyncEntity` instance per provider per ID per run (to avoid recursion)
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
- [ ] Implement shared/default command options
- [ ] Add `ALL` as a `ONE_OF` option if `MultipleAllowed` is set
- [ ] Implement `CliOption` types, e.g. DateTime, file, stream, JsonData
- [ ] Add `ENVIRONMENT VARIABLES` section to help
- [ ] Allow ad-hoc help sections
- [ ] Allow documentation-only nodes

### Console
- [x] Improve default targets so console messages aren't included in redirected output

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
