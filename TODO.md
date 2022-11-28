# TODO

## Tasks

### General
- [ ] Review namespaces and classes before making a v1.0.0 release
  - [ ] Make classes and methods `final` where appropriate
    - Useful regex: `^(.(?!//)(?!abstract )(?!final )(?!private ))*((public|protected|static) )+function`
  - [ ] Replace instantiation via `new` or `DI` with `ContainerInterface->get` where appropriate
  - [ ] Adopt generators and iterators where appropriate
  - [x] Clean up `Exception` namespace
- [ ] Add support for simultaneous requests to `Curler`
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
- ~~Add `SyncException` as a base class~~
- [x] Add `SyncEntityProvider::getResolver()` and `getFuzzyResolver()`
- [ ] Add `SyncStore::checkHeartbeats()`

### Cli
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
- [ ] Validate `ConsoleLevel` values where necessary

### CLI utility
- [ ] Consolidate functionality shared between commands where possible
- [ ] `generate sync`:
  - [ ] Generate entities and providers from OpenAPI specs
- [ ] `generate facade`:
  - [ ] Detect return by reference
- [ ] `http`:
  - [ ] Allow headers to be specified
  - [ ] `--query` should handle duplicate fields

## Snippets

Reinstate when duplicating `ProviderEntity`:

```php
// Detach from the provider servicing the original instance
$this->clearProvider();

// Undeclared properties are typically provider-specific
$this->clearMetaProperties();
```

And when duplicating `SyncEntity`:

```php
$this->Id = null;
```
