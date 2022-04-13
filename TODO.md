# TODO

### General
- [ ] Review namespaces and classes before making a v1.0.0 release
  - [ ] Add `abstract` to classes with exclusively `static` members
- [ ] Formalise tests
  - [ ] Convert informal tests to PHPUnit tests
  - [ ] Adopt PHPStan
    - [ ] Create [extensions](https://phpstan.org/developing-extensions/extension-types) as needed
  - [ ] Write more tests

### Sync

- [ ] Move `Sync\SyncProvider` to `Sync\Provider\SyncProvider`
  - [ ] Create an alias at `Sync\SyncProvider`
- [ ] Implement automatic local storage of entities
- [x] Add optional callback and/or field map parameters to `TConstructible::from()` and `listFrom()`

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
  - [ ] Generate stubs from OpenAPI specs
- [ ] `http`:
  - [ ] Allow headers to be specified
