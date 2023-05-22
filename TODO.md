# TODO

## Current

- [ ] Surface `$this->app()`, `$this->env()`, etc. via `protected` properties too
- [ ] `IProvider`: create an abstract implementation of `ReturnsContainer`, `ReturnsEnvironment`, `ReturnsDescription`
- [ ] Review `ConvertibleEnumeration`: are `protected` constants an option?
- [ ] Review `IConvertibleEnumeration` implementations using `HasConvertibleConstants` for correct case

### Application/CliApplication

- [ ] Update grammar, e.g. use `startSync()` instead of `loadSync()`
- [ ] Remove `getSubcommandSynopsis()` and list subcommands as:

  ```
  SUBCOMMANDS
    <name> - <short description>
  ```

### Sync

- [ ] Create one `SyncEntity` instance per provider per ID per run
- [ ] Establish mechanism for resolving `$<entity>Id` properties to `$Entity` and vice-versa
- [ ] Implement local storage of entities
  - [ ] When a `SyncEntity` is created from provider data, load a local instance before applying provider state
  - [ ] Generate and store deltas while applying provider state
    - [ ] Retain audit log
    - [ ] Track "deltas in" and "deltas out"
  - [ ] Track foreign keys between backends
- [ ] Add `SyncFilterPolicyViolationException`

### `lk-util`

- [ ] `generate sync entity`:
  - [ ] Find first non-`null` value for each property when a list of reference entities is provided
  - [ ] Generate `getDateProperties()` if date properties are detected
  - [ ] Use property order from existing class



## Future/ongoing

- [ ] Replace informal tests with PHPUnit tests
- [ ] Refactor untouched `Dice` methods
- [ ] Remove dependency on `filp/whoops`
- [ ] Split `lkrms/util` into multiple virtual packages
- [ ] Adopt `amphp/parellel=^1` for "multithreading"

### Console

- [ ] Always escape `$msg2`?
- [ ] Adopt `printf`-style syntax? (while maintaining compatibility with current API)
- [ ] Fix inconsistent output from `wordwrap()` when used in conjunction with `Console` markup, e.g. in `CliCommand`

### Container

- [ ] Implement a global "proxy" container that allows container to be swapped without losing bindings?

### Cli

- [ ] Review `Cli` facade: remove? rename? refactor?
- [ ] Implement mutually exclusive options, e.g. `(--tab|--space)`
- [ ] Implement option groups
- [ ] Add `CliOptionValueType::JSON`
- [ ] Add `ENVIRONMENT VARIABLES` section to usage information
- [ ] Review presentation of options for compliance with [these conventions][opengroup]?
- [ ] Highlight or mark default values in vertical lists of values
- [ ] Add support for documentation-only nodes
- [ ] Implement shell completion for Bash, zsh
- [ ] Generate Markdown/HTML/man variants of usage information

### Curler

- [ ] Allow `expiry(null)` to imply no response caching, `expiry(int)` to imply caching
- [ ] Adopt `Curler::with($property, $value): $this`
- [ ] Add support for simultaneous requests
  - [ ] Add `getQ()`, `postQ()` etc. to return a queuable `Curler` instance that will pass the response to a given callback
  - [ ] Add `run()` or similar to process a queue of instances via one `CurlMultiHandle`

### Sync

- [ ] Split `SyncIntrospector`, `SyncIntrospectionClass`, `SyncContext` from `Introspector`, `IntrospectionClass`, `ProviderContext` and make all `final`

### `lk-util`

- [ ] `generate sync`:
  - [ ] Generate entities and provider interfaces from OpenAPI specs
- [ ] `generate facade`:
  - [ ] Detect return by reference
- [ ] `http`:
  - [ ] Allow headers to be specified
  - [ ] Handle duplicate fields passed to `--query`
- [ ] Consolidate functionality shared between commands


[opengroup]: https://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap12.html#tag_12

