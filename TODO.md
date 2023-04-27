# TODO

## Tasks

### General
- [ ] Fix inconsistent output from `wordwrap()` when used in conjunction with `Console` markup, e.g. in `CliCommand`
- [ ] Split `SyncIntrospector`, `SyncIntrospectionClass`, `SyncContext` from `Introspector`, `IntrospectionClass`,
  `ProviderContext` and make all `final`
- [ ] Add support for simultaneous requests to `Curler`
  - [ ] Add `getQ()`, `postQ()` etc. to return a queuable `Curler` instance that will pass the response to a given
    callback
  - [ ] Add `run()` or similar to process a queue of instances via one `CurlMultiHandle`
- [ ] Refactor untouched `Dice` methods
- [ ] Remove dependency on `filp/whoops`
- [ ] Replace informal tests with PHPUnit tests

### Sync
- [ ] Implement lazy hydration
- [ ] Create one `SyncEntity` instance per provider per ID per run
- [ ] Establish mechanism for resolving `$<entity>Id` properties to `$Entity` and vice-versa
- [ ] Implement local storage of entities
  - [ ] When a `SyncEntity` is created from provider data, load a local instance before applying provider state
  - [ ] Generate and store deltas while applying provider state
    - [ ] Retain audit log
    - [ ] Track "deltas in" and "deltas out"
  - [ ] Track foreign keys between backends

### Cli
- [ ] Implement option groups
- [ ] Add `CliOptionValueType::JSON`
- [ ] Add `ENVIRONMENT VARIABLES` section to usage information
- [ ] Add support for documentation-only nodes
- [ ] Add shell completion for Bash, zsh
- [ ] Generate Markdown/HTML/man variants of usage information

### `lk-util`
- [ ] `generate sync entity`:
  - [ ] Use property order from existing class
  - [ ] Find first non-`null` value for each property when a list of reference entities is provided
  - [ ] Generate `getDateProperties()` if date properties are detected
- [ ] `generate sync`:
  - [ ] Generate entities and provider interfaces from OpenAPI specs
- [ ] `generate facade`:
  - [ ] Detect return by reference
- [ ] `http`:
  - [ ] Allow headers to be specified
  - [ ] Handle duplicate fields passed to `--query`
- [ ] Consolidate functionality shared between commands

