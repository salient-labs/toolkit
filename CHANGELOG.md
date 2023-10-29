# Changelog

Notable changes to this project are documented in this file.

It is generated from the GitHub release notes of the project by
[salient/changelog][].

The format is based on [Keep a Changelog][], and this project adheres to
[Semantic Versioning][].

[salient/changelog]: https://github.com/salient-labs/php-changelog
[Keep a Changelog]: https://keepachangelog.com/en/1.1.0/
[Semantic Versioning]: https://semver.org/spec/v2.0.0.html

## [v0.20.80] - 2023-10-26

### Changed

- Sync: allow context objects to be passed to entity resolvers
  - Allow filters and context values to be applied to `READ_LIST` operations performed by entity resolvers
  - Optionally return context values from `ISyncContext::getFilter()` and `claimFilter()` if there is no matching filter (enabled by default)
  - Use `null` as the default uncertainty threshold

## [v0.20.79] - 2023-10-25

### Added

- Sync: return cached entities from `SyncEntityProvider::get()` if possible

`ISyncEntity`:
- Add static utility method `idFromNameOrId()` to simplify resolution of user-provided values that may be identifiers or names

### Changed

`ISyncEntityProvider`:
- Extend `ReturnsProvider`
- Add `entity()`
- In `getResolver()`, make `$nameProperty` nullable

`SyncEntityFuzzyResolver`:
- Throw an exception if `$requireOneMatch` is used without specifying how to narrow the list of potential matches
- If `$RequireOneMatch` is `true`, always apply an uncertainty threshold to the `SAME` and `CONTAINS` algorithms to ensure the list of potential matches is narrowed
- If `$nameProperty` is `null`, use a `SyncIntrospector` closure to get entity names

- `SyncEntityNotFoundException`: accept a filter array in lieu of an id

## [v0.20.78] - 2023-10-24

### Added

- Add `Compute::ngramSimilarity()`, `ngramIntersection()` and `ngrams()`
- Add `RegularExpression::MONGODB_OBJECTID`
- Sync: add `ISyncProvider::isValidIdentifier()`

### Changed

- Remove `Compute` facade and rename `Computations` to `Compute`
- Rename `TextSimilarityAlgorithm` to `TextComparisonAlgorithm` and build out
- Sync: refactor `SyncEntityFuzzyResolver`
  - Allow multiple text comparison algorithms to be used to match entities
  - Skip algorithms where no entities are matched, only returning `null` if every algorithm is skipped
  - Calculate uncertainty once per entity per call to `getByName()` instead of once per call to the `usort` callback
  - Optionally return `null` if more than one entity is matched
- Sync: merge `ISyncEntityProvider::getFuzzyResolver()` into `getResolver()`
- PhpDoc: don't return `true` from `hasDetail()` for a `@readonly` tag
- `generate builder`: make inclusion of writable properties in builders optional

### Fixed

- Sync: fix invalid request URLs by applying `rawurlencode()` to named parameters
- PhpDoc: fix issue where variadic `@param` tags are not parsed

## [v0.20.77] - 2023-10-23

### Changed

- Add `getFuzzyResolver()` to `ISyncEntityProvider`

## [v0.20.76] - 2023-10-23

### Changed

- Review `Curler` exceptions:

  - Add `CurlerCurlErrorException`, `CurlerHttpErrorException`, `CurlerInvalidResponseException`, `CurlerUnexpectedResponseException`
  - Move `CurlerException::getStatusCode()` and `getReasonPhrase()` to `CurlerHttpErrorException`
  - Make `CurlerException` abstract

- Sync: cache successful heartbeat checks in `HttpSyncProvider::checkHeartbeat()` and `DbSyncProvider::checkHeartbeat()`
- Sync: catch connectivity-related exceptions and throw `SyncProviderBackendUnreachableException` in `HttpSyncProvider::checkHeartbeat()` and `DbSyncProvider::checkHeartbeat()`
- Sync: in `HttpSyncProvider`, make `checkHeartbeat()` final and add overridable `getHeartbeat()` so providers don't need to implement their own caching or exception handling
- Sync: throw HTTP "resource not found" errors as `SyncEntityNotFoundException`
- Sync: only catch `MethodNotImplementedException` and `SyncProviderBackendUnreachableException` in `SyncStore::checkHeartbeats()`

## [v0.20.75] - 2023-10-22

### Changed

- Add `ProxyBasePath` to `HttpServer`

## [v0.20.74] - 2023-10-19

### Changed

- Rename `DirectoryIterator` to `RecursiveFilesystemIterator` to avoid confusion with the SPL iterator of the same name
- `FluentIteratorInterface`: extend `Traversable` instead of `Iterator` so the interface can be implemented by `IteratorAggregate` classes
- Implement `FluentIteratorInterface` in `RecursiveFilesystemIterator`
- Return a `RecursiveFilesystemIterator` from `File::find()`

### Deprecated

- **Deprecate all arguments to `File::find()`, which will return an empty `RecursiveFilesystemIterator` in an upcoming release**

## [v0.20.73] - 2023-10-18

### Added

- Add `File::open()` and `File::close()`

### Changed

- Make `Filesystem` methods static
- Add `DirectoryIterator` (will replace `File::find()`)
- Finalise `Iterator` cleanup
- In `TCollection`, don't return a clone if the collection is unchanged

### Fixed

- Fix issue where `File::isPhp()` returns `true` for XML files

## [v0.20.72] - 2023-10-17

### Added

- Add `Arr::notNull()`, `notEmpty()`
- Add `Env::environment()`
  - Use `app_env` for environment selection and `PHP_ENV` as a fallback
- Add `MockTarget` for console output testing
- Sync: Allow `SyncEntity` objects to be serialized, e.g. for caching
  - Surface declared and "magic" property names that are both readable and writable via `Introspector::$SerializableProperties`
  - Add `SyncStore::getProviderHash()` and `getProvider()` so sync providers can be serialized by hash
  - Add `SyncEntity::__serialize()` and `__unserialize()`

### Changed

- Move from `Convert` to `Str`:
  - `splitAndTrim()`
  - `splitAndTrimOutsideBrackets()`
  - `splitOutsideBrackets()`
- `Arr`:
  - Consolidate `sort()`, `asort()`, `usort()`, `uasort()` into `sort()`
  - Consolidate `rsort()`, `arsort()` into `sortDesc()`
  - Consolidate `ksort()`, `uksort()` into `sortByKey()`
  - Rename `krsort()` to `sortByKeyDesc()`
- `PhpDoc`: improve blank line handling, discard empty docblocks
- `ConsoleWriter`:
  - Reuse existing STDOUT/STDERR targets so `MockTarget` isn't discarded when a command calls `Console::registerStderrTarget()`
  - Reinstate previous STDOUT/STDERR targets after deregistering a target if possible
- Exceptions:
  - Allow `Lkrms\Exception\Exception` to be thrown
  - `Container`: move exceptions to `Container\Exception` namespace
  - `Curler`: update signature of `CurlerException::__construct()`

### Removed

- Remove support for `env` variable for environment selection
- Remove `Arr::natsort()`, `Arr::natcasesort()`, `Arr::trimAndCompact()`

### Fixed

- `Str`: fix issue where `splitAndTrim()` and `splitAndTrimOutsideBrackets()` remove non-empty strings, e.g. `"0"`

## [v0.20.71] - 2023-10-12

- Sync: fix issue where single-use generators are applied to relationships
- Sync: allow providers to return backend-dependent date formatters without recursion by surfacing the cached formatter via:
  - `Provider::getCachedDateFormatter()`
  - `Provider::setDateFormatter()`

## [v0.20.70] - 2023-10-12

### Added

- Add `Arrayable` interface

### Changed

- Move `Iterator` namespace from `Lkrms\Support` to `Lkrms`
- Rename iterators:
  - `ObjectOrArrayIterator` => `GraphIterator`
  - `RecursiveObjectOrArrayIterator` => `RecursiveGraphIterator`
  - `RecursiveHasChildrenCallbackIterator` => `RecursiveCallbackIterator`
- Replicate `GraphIterator` as `MutableGraphIterator` and remove `MutableIterator` implementation from `GraphIterator`
- Ditto for `RecursiveGraphIterator` and `RecursiveMutableGraphIterator`
- In graph iterators, throw an exception instead of iterating over unknown `Traversable` objects
- `FluentIteratorInterface`:
  - Extend new `Arrayable` interface
  - Add optional `$preserveKeys` parameter to `toArray()`
  - Rename `forEachWhileTrue()` to `forEachWhile()`
- Clean up `File::find()`

### Fixed

- Fix `Convert::walkRecursive()` issue where the wrong inner iterator is returned

## [v0.20.69] - 2023-10-11

### Added

- Sync: allow one closure to override multiple operations by using bitmasks in the `overrides()` array passed to sync definition builders
- Sync: add `SyncProvider::callbackPipeline()` for convenience

### Changed

- Sync: improve date formatter handling

  - Adopt `IDateFormatter` in more locations
  - Receive and/or propagate date formatters for `Curler` via `HttpSyncProvider::getCurler()`
  - Pass `$path` to `HttpSyncProvider::getDateFormatter()`

### Fixed

- Fix issue where `RecursiveHasChildrenCallbackIterator` is only effective on children of the root node
  - This issue caused `Curler` to fail to serialize `CurlerFile` and `DateTimeInterface` instances in request data
- Fix inconsistent return type of `RecursiveObjectOrArrayIterator::maybeReplaceCurrentWithArray()`
- Fix iterator-related bugs in `Curler::prepareData()`

## [v0.20.68] - 2023-10-11

### Added

- Sync: replace named parameters in endpoint paths with context values

  - Resolve parameters like `groupId` in `"/group/:groupId/users"` from the `ISyncContext` received by the operation
  - Convert names to snake_case and remove `_id` suffixes for comparison
  - Apply entity ID to `:id` if it appears in an endpoint path
  - Throw an exception if the value of a named parameter contains a forward slash
  - Allow multiple endpoint paths per entity

- Sync: allow `Curler` behaviour to be customised from within HTTP sync definitions via `curlerProperties()`
- Sync: add `keyMap()` and `readFromReadList()` to sync definition builders for cleaner, more expressive grammar
- Sync: allow entity provider interfaces to cover multiple entities
- Detect native `DateTimeInterface` properties during introspection
- Add `IDateFormatter`

- `IProviderContext`/`ProviderContext`:

  - Reinstate ad-hoc value propagation via `withValue()`, `getValue()`, `hasValue()`
  - Add `last()` for quicker access to the stack

### Changed

- Sync: don't defer creation of related entities if non-scalar data is available
- Sync: throw an exception if `ISyncEntity` relationships target classes that don't implement `ISyncEntity`
- Sync: apply entity ID to HTTP `CREATE`, `UPDATE` and `DELETE` operation URLs in addition to `READ`
- Sync: rework context propagation and generic types
- Sync: document provider requirements and entity class mapping
- Rename `Reflection` to `Reflect` and remove the facade with the same name
- Review `Reflect` and add support for DNF types

### Removed

- Remove `Reflect::getClassesBetween()`

## [v0.20.67] - 2023-09-27

### Changed

- `Convert`: improve handling of blank lines in `linesToLists()`

## [v0.20.66] - 2023-09-27

### Changed

- `Convert`: add support for multi-line items to `linesToLists()`

## [v0.20.65] - 2023-09-26

### Changed

- Cli: improve argument handling

  - Allow options and positional arguments appearing before `--` to be given in an arbitrary order
  - Improve empty string handling so `--option ''` is taken as a value and `--option=` clears default or preceding values
  - Fix issue where options with an optional value are not always returned by `getEffectiveArgument()`
  - Fix issue where `"-"` cannot be given as a positional argument before `"--"`
  - Check for `null` and empty strings explicitly to prevent unintended behaviour when `"0"` is used as a short option or given as a value

- Refactor `Builder`

  - Rename `getClassName()` to `getService()`
  - Replace overloaded methods with declared ones:
    - `build()`
    - `resolve()`
    - `get()`
    - `isset()`
    - `go()`
  - Split `HasBuilder` **interface** into `ReturnsBuilder`, `ResolvesBuilder` and others
  - Add `HasBuilder` **trait** to simplify builder servicing
  - Update "lk-util generate builder" command
    - Allow properties to be excluded from "lk-util generate builder" output
    - Allow methods to be forwarded from a builder to a new instance without calling `$builder->go()` first
  - Rename `Builder::get()` to `getB()` and `isset()` to `issetB()` so instance methods with these names can be surfaced
  - Adopt method forwarding for `CurlerBuilder`

  Also:
  - Make both callbacks in `IFluentInterface::if()` optional

## [v0.20.64] - 2023-09-24

### Changed

- Rename `IHierarchy` to `ITreeable` and build out
  - Require hierarchical entities to return parent and children properties
  - Create implicit relationships between parents and children
- Sync: reinstate `_id` and `_ids` matching for properties without relationships
- Sync: fall back to deferral of `$this->service()` instead of `static::class` in `SyncEntity::defer()` when no entity is specified
- Sync: automatically defer incoming entities if possible
- Sync: Add support for resolution of deferred entities from providers
  - Add `Sync::resolveDeferredEntities()` to retrieve deferred entities from providers and/or the local entity store
    - Attempt to load entities via `getListA()` with an `id` filter, but if the provider doesn't implement this operation or doesn't claim the filter value, retrieve them individually
  - Add support for "fully offline" and "fully online" handling of cached entities (implementation pending)
  - Actually call `IProvidable::postLoad()`
- Sync: review context handling
  - Add provider to `IProviderContext`
  - Add `getContext()` to `IProvider`
  - Simplify context propagation in `TProvidable`, `Introspector`, `SyncEntity`, `SyncProvider`, `SyncEntityProvider`, `SyncIntrospector`
  - Remove unused `set()` and `get()` methods from `IProviderContext`
  - Rename `claimFilterValue()` to `claimFilter()`
  - Rename `getFilter()` to `getFilters()`
  - Add `DeferredSyncEntityPolicy` enumeration
  - Add `withDeferredSyncEntityPolicy()` and `getDeferredSyncEntityPolicy()` to `ISyncContext`
  - Allow entities resolved by `SyncStore::resolveDeferredEntities()` to be scoped to entities deferred since a checkpoint returned by `SyncStore::getDeferredEntityCheckpoint()`
  - Apply deferred sync entity policies in `SyncEntityProvider`
- Sync: review `SyncError` and `SyncErrorCollection`
  - Move sync error reporting to `SyncStore`
  - Remove parameters from `IApplication::stopSync()`
- Sync: review `SqlQuery` and `DbSyncProvider` classes
- Sync: rename `SyncSerializeLinkType` to `SyncEntityLinkType`
- Sync: fix `SyncStore` issue where `IFacade::unload()` is not always called on `close()`
- Console: remove unnecessary method `ConsoleLevel::toCode()`
- Replace `?? null` constructs with `isset()` where possible

## [v0.20.63] - 2023-09-22

### Changed

- Replace Whoops with new `ErrorHandler` class

## [v0.20.62] - 2023-09-21

### Changed

- Add `--check` option to `lk-util generate` commands, and print diffs instead of creating or replacing ".generated.php" files
- Review timer functions in `System`
- Review `Application`/`IApplication`:
  - Accept optional `$appName` via constructor
  - Rename methods:
    - `inProduction()` -> `isProduction()`
    - `logConsoleMessages()` -> `logOutput()`
    - `loadCache()` -> `startCache()`
    - `loadCacheIfExists()` -> `resumeCache()`
    - `unloadCache()` -> `stopCache()`
    - `loadSync()` -> `startSync()`
    - `unloadSync()` -> `stopSync()`
    - `writeResourceUsage()` -> `reportResourceUsage()`
    - `writeTimers()` -> `reportTimers()`
  - `isProduction()`: check environment variable `env` for value `"production"`
  - `logOutput()`: reverse order of parameters
  - `stopSync()`: replace `$silent` with `$reportErrors` and add `$exitStatus`
  - `registerShutdownReport()`: only suppress timers when `$timerTypes` is an empty array
  - Register one shutdown report per run, no matter how many service containers are created
  - Fix issue where calling `get<dir>Path()` with `$create = false` precludes creation of the directory when subsequently called with `$create = true`
  - Improve documentation

## [v0.20.61] - 2023-09-20

### Changed

- Sync: improve entity deferral and implement relationships between entities

## [v0.20.60] - 2023-09-18

### Changed

- Sync: service one instance of each entity per run
- Sync: add initial implementation of entity deferral
- Sync: review `SyncEntity`, `SyncProvider` and related classes/interfaces/traits

## [v0.20.59] - 2023-09-16

### Changed

- Container: pass `Env` flags from an optional `Application::__construct()` parameter to `Env::apply()`
- Env: improve timezone handling
  - Suppress error output when testing the validity of timezones found in the environment
  - Convert sparsely documented but seemingly ubiquitous `TZ` value "UTC0" to "UTC" because PHP supports legacy "GMT0" timezone but not "UTC0"
- Allow `File::find()` to run over multiple directories

## [v0.20.58] - 2023-09-14

### Changed

- Cli: allow default values to be hidden from generated documentation

### Fixed

- Cli: fix issue where mandatory options are not shown in collapsed synopses

## [v0.20.57] - 2023-09-13

### Added

- `Cli`: generate Markdown and man page documentation from help messages
- `Cli`: improve help message presentation and formatting

### Changed

- `Sync`: add `SyncFilterPolicyViolationException`

## [v0.20.56] - 2023-09-06

### Deprecated

- Deprecate `Convert::lineEndingsToUnix()`

### Fixed

- Fix regression in `File::getEol()`

## [v0.20.55] - 2023-09-06

### Changed

- Add `Str::setEol()` and standardise `getEol()` methods

## [v0.20.54] - 2023-09-04

### Changed

- Allow `CliOption` value names to contain arbitrary characters

[v0.20.80]: https://github.com/lkrms/php-util/compare/v0.20.79...v0.20.80
[v0.20.79]: https://github.com/lkrms/php-util/compare/v0.20.78...v0.20.79
[v0.20.78]: https://github.com/lkrms/php-util/compare/v0.20.77...v0.20.78
[v0.20.77]: https://github.com/lkrms/php-util/compare/v0.20.76...v0.20.77
[v0.20.76]: https://github.com/lkrms/php-util/compare/v0.20.75...v0.20.76
[v0.20.75]: https://github.com/lkrms/php-util/compare/v0.20.74...v0.20.75
[v0.20.74]: https://github.com/lkrms/php-util/compare/v0.20.73...v0.20.74
[v0.20.73]: https://github.com/lkrms/php-util/compare/v0.20.72...v0.20.73
[v0.20.72]: https://github.com/lkrms/php-util/compare/v0.20.71...v0.20.72
[v0.20.71]: https://github.com/lkrms/php-util/compare/v0.20.70...v0.20.71
[v0.20.70]: https://github.com/lkrms/php-util/compare/v0.20.69...v0.20.70
[v0.20.69]: https://github.com/lkrms/php-util/compare/v0.20.68...v0.20.69
[v0.20.68]: https://github.com/lkrms/php-util/compare/v0.20.67...v0.20.68
[v0.20.67]: https://github.com/lkrms/php-util/compare/v0.20.66...v0.20.67
[v0.20.66]: https://github.com/lkrms/php-util/compare/v0.20.65...v0.20.66
[v0.20.65]: https://github.com/lkrms/php-util/compare/v0.20.64...v0.20.65
[v0.20.64]: https://github.com/lkrms/php-util/compare/v0.20.63...v0.20.64
[v0.20.63]: https://github.com/lkrms/php-util/compare/v0.20.62...v0.20.63
[v0.20.62]: https://github.com/lkrms/php-util/compare/v0.20.61...v0.20.62
[v0.20.61]: https://github.com/lkrms/php-util/compare/v0.20.60...v0.20.61
[v0.20.60]: https://github.com/lkrms/php-util/compare/v0.20.59...v0.20.60
[v0.20.59]: https://github.com/lkrms/php-util/compare/v0.20.58...v0.20.59
[v0.20.58]: https://github.com/lkrms/php-util/compare/v0.20.57...v0.20.58
[v0.20.57]: https://github.com/lkrms/php-util/compare/v0.20.56...v0.20.57
[v0.20.56]: https://github.com/lkrms/php-util/compare/v0.20.55...v0.20.56
[v0.20.55]: https://github.com/lkrms/php-util/compare/v0.20.54...v0.20.55
[v0.20.54]: https://github.com/lkrms/php-util/releases/tag/v0.20.54