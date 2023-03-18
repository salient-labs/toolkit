# TODO

## Tasks

### General
- [ ] Review namespaces and classes before making a v1.0.0 release
  - [ ] Make classes and methods `final` where appropriate
    - Useful regex: `^((.(?!//)(?!abstract )(?!final )(?!private ))*((public|protected|static) )+function|([a-z]+ )*class)`
  - [ ] Replace instantiation via `new` or `DI` with `ContainerInterface->get` where appropriate
  - [ ] Adopt generators and iterators where appropriate
- [ ] Add support for simultaneous requests to `Curler`
  - [ ] Add `getQ()`, `postQ()` etc. to return a queuable `Curler` instance that will pass the response to a given callback
  - [ ] Add `run()` or similar to process a queue of instances via one `CurlMultiHandle`
- [ ] Finalise `Dice` refactor
- [ ] Convert informal tests to PHPUnit tests
- [ ] Increase code coverage

### Documentation
- [ ] Add missing descriptions

### Sync
- [ ] Implement lazy hydration
- [ ] Create one `SyncEntity` instance per provider per ID per run (to avoid recursion)
- [ ] Establish mechanism for resolving `$<entity>Id` properties to `$Entity` and vice-versa
- [ ] Implement automatic local storage of entities
  - [ ] When a `SyncEntity` is created, load a local instance before applying provider state
  - [ ] Generate and store deltas while applying provider state
    - [ ] Retain audit log
    - [ ] Track "deltas in" and "deltas out"
  - [ ] Track foreign keys between backends
- [x] Add `SyncStore::checkHeartbeats()`

### Cli
- [ ] Implement shared/default command options
- [x] Add `ALL` as a `ONE_OF` option if `MultipleAllowed` is set
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
- [ ] Allow documentation-only nodes
- [ ] In `CliCommand::prepareDescription()`, improve accuracy of `wordwrap()` when formatting markup is used

### Console
- [ ] Add `ConsoleFormatter::removeTags()` variant that returns tag reinsertion data, e.g. for `CliCommand::prepareDescription()`

### CLI utility
- [ ] `generate sync entity`:
  - [ ] Use property order from existing class if possible
    - [ ] Allow order to be reset via command line option
  - [ ] Find first non-`null` value for each property when a list of reference entities is provided
  - [ ] Generate `getDateProperties()` if date properties are detected
- [ ] Consolidate functionality shared between commands where possible
- [ ] `generate sync`:
  - [ ] Generate entities and providers from OpenAPI specs
- [ ] `generate facade`:
  - [ ] Detect return by reference
- [ ] `http`:
  - [ ] Allow headers to be specified
  - [ ] `--query` should handle duplicate fields

