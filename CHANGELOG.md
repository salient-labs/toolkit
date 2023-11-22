# Changelog

Notable changes to this project are documented in this file.

It is generated from the GitHub release notes of the project by
[salient/changelog][].

The format is based on [Keep a Changelog][], and this project adheres to
[Semantic Versioning][].

[salient/changelog]: https://github.com/salient-labs/php-changelog
[Keep a Changelog]: https://keepachangelog.com/en/1.1.0/
[Semantic Versioning]: https://semver.org/spec/v2.0.0.html

## [v0.21.5] - 2023-11-22

### Added

- Add initial event class hierarchies
- Add `IStoppableEvent` interface
- Reinstate parts of removed `StoppableServiceEvent` class as new `TStoppableEvent` trait
- Add `Assert::isArray()`, `Assert::isInt()`, `Assert::isString()`
- Add `Cache::getArray()`, `Cache::getInt()`, `Cache::getString()`
- Add `Inspect::getType()`
- Add ASCII-only `Str::lower()` and `Str::upper()` methods
- Add `ExceptionInterface` and `ExceptionTrait` to simplify inheritance of native exception classes
- Add `InvalidArgumentException`, `UnexpectedValueException`, `InvalidContainerBindingException` and adopt where appropriate
- Add a robust `Uri` class ahead of upcoming `Curler` improvements

### Changed

- Refactor `OAuth2Client` as an `abstract` class
- Refactor `*Collection` interfaces, traits and classes, moving some functionality to new `*List` or `*ListCollection` counterparts
- Remove type checks from `TypedCollection`
- In collection classes, return `null` instead of `false` when there is no item to return
- In `Cache::getInstanceOf()`, return `null` instead of `false` if there is no item to return (for consistency with other `get<type>()` methods)
- In `Curler`, derive `$cacheResponse` from `$expiry` if an explicit expiry is given
- Dispatch `GlobalContainerSetEvent` and `SyncStoreLoadedEvent` instead of named `ServiceEvent`s
- In `IProvider` and `ISyncEntity`, extend `HasName` instead of `HasDescription` to remove requirement for unused `description()` method
- Rename `Assert` methods:
  - `patternMatches()` -> `isMatch()`
  - `sapiIsCli()` -> `runningOnCli()`
  - `argvIsRegistered()` -> `argvIsDeclared()`

### Removed

- Remove `OAuth2Provider` (unnecessary with `OAuth2Client`'s closure-based workaround for access to protected values)
- Remove redundant `<service>::EVENT_*` constants
- Remove unnecessary `ServiceEvent` and `StoppableServiceEvent` classes
- Remove unused `Assert::localeIsUtf8()` method
- Remove legacy `Trash` class
- Remove `HasMutator` trait (replaced with `Immutable`)
- Remove `LooselyTypedCollection` (use `TypedCollection` instead)

### Fixed

- Fix code generator issue where `\self` and `\static` return types are not resolved correctly

## [v0.21.4] - 2023-11-17

### Added

- `lk-util`: add first cut of `generate tests` command
- Add `Cache::asOfNow()` to mitigate race conditions arising from expiry of items between subsequent calls to `Cache::has()` and `Cache::get()`
- Add `Cache::getInstanceOf()`, `Cache::getItemCount()` and `Cache::getAllKeys()`

- Implement PSR-14
  - `EventDispatcher`:
    - Implement `EventDispatcherInterface` and `ListenerProviderInterface`
    - Optionally compose a separate `ListenerProviderInterface`
    - Rework `dispatch()` and listener signatures for PSR-14 compliance
  - Add `Reflect::getFirstCallbackParameterClassNames()` (required for event listener autowiring)
  - Add `ServiceEvent` and `StoppableServiceEvent`

- Add methods:
  - `Arr::listOfArrayKey()`
  - `Arr::listOfInt()`
  - `Arr::listOfString()`
  - `Arr::trimAndImplode()`
  - `Assert::instanceOf()`

### Changed

- In `Arr::trim()`, remove empty strings by default
- In `Cache::set()` and `Cache::maybeGet()`, accept `DateTimeInterface` expiration times
- Rename `InvalidRuntimeConfigurationException` to `IncompatibleRuntimeEnvironmentException`
- Add `HasName` interface and move `HasDescription::name()` to `HasName`
- Remove nullability from return types of `HasName::name()` and `HasDescription::description()`

- Move and/or refactor methods, deprecating the original:
  - `Convert::sparseToString()` -> `Arr::implodeNotEmpty()`
  - `Convert::flatten()` -> `Arr::unwrap()`
  - `Convert::toArray()` -> `Arr::wrap()`
  - `Convert::toList()` -> `Arr::listWrap()`
  - `Convert::toUniqueList()` -> `Arr::unique()`
  - `Convert::toDateTimeImmutable()` -> `Date::immutable()`
  - `Test::isListArray()` -> `Arr::isList()`
  - `Test::isIndexedArray()` -> `Arr::isIndexed()`
  - `Test::isArrayOfArrayKey()` -> `Arr::ofArrayKey()`
  - `Test::isArrayOfInt()` -> `Arr::ofInt()`
  - `Test::isArrayOfString()` -> `Arr::ofString()`

- Rename methods:
  - `Arr::notNull()` -> `whereNotNull()`
  - `Arr::notEmpty()` -> `whereNotEmpty()`
  - `Arr::implodeNotEmpty()` -> `implode()`

- Rename `Returns*` interfaces to `Has*` to simplify grammar:
  - `ReturnsContainer` -> `HasContainer`
  - `ReturnsDescription` -> `HasDescription`
  - `ReturnsEnvironment` -> `HasEnvironment`
  - `ReturnsIdentifier` -> `HasIdentifier`
  - `ReturnsProvider` -> `HasProvider`
  - `ReturnsProviderContext` -> `HasProviderContext`
  - `ReturnsService` -> `HasService`

- `Introspector::getGetNameClosure()`:
  - Remove nullability from closure return type
  - Do not fall back to `description` when there are no name properties
  - Return `"#$id"` when falling back to `id`
  - Fix issue where closures may return types other than `string`
  - Fix issue where closures fail to resolve first and last name pairs when normalisers other than snake_case are used

### Removed

- Remove unused methods:
  - `Convert::toUnique()`
  - `Convert::columnsToUniqueList()`
  - `Test::isAssociativeArray()`
  - `Test::isArrayOfValue()`
  - `Reflect::getAllTraits()`

### Fixed

- Fix builtin type handling in `Reflect::getTypeDeclaration()`

## [v0.21.3] - 2023-11-11

### Added

- Add `File::relativeToParent()`
- Add `Assert::fileExists()`, `Assert::isFile()`, `Assert::isDir()`
- Add `AssertionFailedException`
- Add `Timekeeper` class
- Add `Profile` facade for `Timekeeper`

### Changed

- Rename `Lkrms\Utility\Composer` to `Lkrms\Utility\Package` and rename methods:

  - `hasDevDependencies()` -> `hasDevPackages()`
  - `getRootPackageName()` -> `name()`
  - `getRootPackageReference()` -> `reference()`
  - `getRootPackageVersion()` -> `version()`
  - `getRootPackagePath()` -> `path()`
  - `getPackageReference()` -> `packageReference()`
  - `getPackageVersion()` -> `packageVersion()`
  - `getPackagePath()` -> `packagePath()`
  - `getClassPath()` -> `classPath()`
  - `getNamespacePath()` -> `namespacePath()`

- Rename `Lkrms\Utility\Assertions` to `Lkrms\Utility\Assert`

### Removed

- Remove `Composer` facade
- Remove `Assert` facade
- Remove timer implementation from `System` (moved to `Timekeeper`)

## [v0.21.2] - 2023-11-07

### Fixed

- Sync: fix issue where empty child relationships do not always resolve
  - If a child relationship resolves to an empty list, assign it directly to the children property because `addChild()` will not be called

## [v0.21.1] - 2023-11-07

### Changed

- Sync: remove superfluous propagation of `$offline`
- Sync: Add optional `$entityType` parameter to `Sync::resolveDeferred()`

## [v0.21.0] - 2023-11-06

### Changed

- Sync: rename `HydrationFlag` to `HydrationPolicy` and rework related methods

  `ISyncContext`:
  - Rename:
    - `withHydrationFlags()` -> `withHydrationPolicy()`
    - `maybeApplyFilterPolicy()` -> `applyFilterPolicy()`
    - `getHydrationFlags()` -> `getHydrationPolicy()`
  - Add:
    - `online()`
    - `offline()`
    - `offlineFirst()`
    - `getOffline()`

  `ISyncEntityProvider`:
  - Rename:
    - `withoutResolvingDeferrals()` -> `doNotResolve()`
    - `withoutHydration()` -> `doNotHydrate()`
    - `withHydration()` -> `hydrate()`
  - Add:
    - `resolveEarly()`
    - `resolveLate()`
    - `offlineFirst()`

  `SyncEntityProvider`:
  - Track online/offline status in `ISyncContext`
  - Remove deprecated `getFuzzyResolver()`

## [v0.20.89] - 2023-11-06

### Fixed

- Create a separate cURL handle for each request that returns paginated data via a generator to resolve errors arising from multiple `Curler` instances sharing one handle

## [v0.20.88] - 2023-11-06

### Added

- Add `IPipeline::cc()` to allow more flexible workflows, e.g. in sync entity pipelines
- Add `IPipeline::collectThenIf()` for completeness

## [v0.20.87] - 2023-11-05

### Added

- Sync: add `SyncInvalidRequestException`

### Changed

- Sync: improve entity deferral and hydration

  - Optionally limit hydration flag scope to a given depth
  - Add `ISyncEntityProvider::withoutResolvingDeferrals()`, `withoutHydration()` and `withHydration()` to simplify manipulation of the underlying context
  - `Sync::deferredEntity()`: ignore hydration flags in favour of deferral policy
  - `Sync::resolveDeferred()`: resolve relationships first to take advantage of more entities per round trip
  - In `SyncEntityResolver::getByName()`, catch `SyncFilterPolicyViolationException` and make a second attempt without the filter
  - When resolving named parameters in `HttpSyncDefinition::runHttpOperation()`, claim matching **filters** before checking for matching **values**, reversing the previous order

- `lk-util generate sync entity`:

  - Generate sync entities with relationships and parent/child properties
  - Allow properties in the reference entity to be skipped
  - Treat entity properties with suffix `_id` or `_ids` as relationships

- `Convert::valueToCode()`: improve string escaping and add support for multiline arrays
- Declare `Builder::getTerminators()` so subclasses don't need to

### Removed

- Sync: remove dangerous `HydrationFlag::NO_FILTER` option

### Fixed

- Sync: fix issue where deferred entities that are immediately resolved by the provider are not assigned to the variable originally passed by reference
- Sync: fix issue where checks are performed against the child of the intended context during entity and relationship deferral
- Sync: fix issue where hydration flags are incorrectly performed on the receiving entity
- Fix `CliCommand::getEffectiveArgument()` issue where short arguments with name `"0"` are not returned correctly

## [v0.20.86] - 2023-11-03

### Added

- Sync: implement hydration of relationships

  - Add `HydrationFlag`
  - Allow hydration flags to be applied to sync contexts globally or per-entity
  - Implement suppressed, lazy, deferred and eager hydration of relationships
  - Apply parent/child relationships via `addChild()`/`setParent()`
  - Register entities with the entity store before processing deferred entities and relationships to prevent race conditions and infinite recursion
  - Add magic methods to `DeferredSyncEntity` for on-demand resolution of deferred entities (similar to lazy hydration implemented via `IteratorAggregate` in `DeferredRelationship`)
  - Add `Sync::resolveDeferred()`
  - Allow deferred entities and relationships to be resolved via callback instead of assignment
  - Store resolved entities and relationships in `DeferredSyncEntity` and `DeferredRelationship` so they can forward property actions and method calls until they go out of scope
  - Throw an exception if an attempt is made to resolve the same deferred entity or relationship multiple times
  - In `Sync::resolveDeferredEntities()`, remove attempt to resolve multiple entities via `getListA()` in favour of resolving the first instance of each entity **in its own context** to ensure parent entities are surfaced to providers

- Sync: add protected `DbSyncProvider::first()` method to simplify retrieval of a single entity
- Add `Convert::toValue()`
- Add `Test::isFloatValue()`

### Changed

- Sync: simplify filter policy API

  - Add `ISyncProvider::getFilterPolicy()` so providers can specify a default without implementing `getDefinition()`
  - Add `SyncProvider::run()` to minimise the need for boilerplate safety checks in providers where sync operations are performed by declared methods

- Sync: improve error reporting

  - Remove `$toConsole` parameter from `Sync::error()`
  - Add `Sync::enableErrorReporting()` and `disableErrorReporting()`
  - Fix issue where output from `Sync::reportErrors()` is not unescaped

- Sync: rename classes and methods:

  - `DeferredSyncEntity` -> `DeferredEntity`
  - `DeferredSyncEntityPolicy` -> `DeferralPolicy`
  - `SyncFilterPolicy` -> `FilterPolicy`
  - `Sync::getDeferredEntityCheckpoint()` -> `getDeferralCheckpoint()`
  - `ISyncContext::withDeferredSyncEntityPolicy()` -> `withDeferralPolicy()`
  - `ISyncContext::getDeferredSyncEntityPolicy()` -> `getDeferralPolicy()`

- Add optional `$count` parameter to `Console::message{,Once}()`
- `DbConnector`: use `DB2CODEPAGE` to enable UTF-8 before connecting to Db2
- `DbSyncProvider`: remove UTF-8 locale assertion
- `Convert`/`Test`: accept leading and trailing spaces in integer and boolean strings

### Removed

- Remove unused entity deferral methods from `SyncEntity` and `SyncEntityProvider`
- Remove references to `DeferredSyncEntity::$Entity`'s unsupported nullability

### Fixed

- In `ConsoleFormatter::escapeTags()`, mitigate `PREG_JIT_STACKLIMIT_ERROR` when printing long `Console` messages with many special characters (e.g. JSON-encoded values) by only escaping recognised tag delimiters
- Fix `Event::listen()` callback signature

## [v0.20.85] - 2023-10-31

### Changed

- ICliApplication: rework to allow chained methods after `run()`
  - Return `$this` from `ICliApplication::run()`
  - Surface the most recent return value via `ICliApplication::getLastExitStatus()`
  - Add `ICliApplication::exit()`

## [v0.20.84] - 2023-10-31

### Added

- Add `Inflect` class with `indefinite()` method that determines which indefinite article ("a" or "an") to use before a word
- Add `Arr::first()` and `Arr::last()`

### Changed

`generate sync entity` command:
- Add a default description to entity classes

`generate sync provider` command:
- Only use `FluentIteratorInterface` as a magic method return type

- Add `.gitattributes` file to reduce package size

### Removed

- Remove `--extend` option from `generate sync provider` command

## [v0.20.83] - 2023-10-30

### Added

- Add `FilesystemErrorException` and throw it instead of returning `false` from (most) `File` methods
- Add `InvalidRuntimeConfigurationException`
- Add `Graph::from()` to allow passing the initial object or array by value

### Changed

- Rename `Filesystem` to `File`
- Finalise deprecation of `File::find()` as a standalone method, replacing it with a `RecursiveFilesystemIterator()` factory
- Rename methods:
  - `File::createTemporaryDirectory()` -> `createTempDir()`
  - `File::maybeCreate()` -> `create()`
  - `File::maybeCreateDirectory()` -> `createDir()`
  - `File::maybeDelete()` -> `delete()`
  - `File::maybeDeleteDirectory()` -> `deleteDir()`
  - `File::pruneDirectory()` -> `pruneDir()`
  - `Graph::getInnerGraph()` -> `inner()`

### Removed

- Remove `File` facade (`Lkrms\Utility\File` is a drop-in replacement after adopting the method names above)

### Fixed

- Make non-strict comparisons in `File` strict

## [v0.20.82] - 2023-10-29

### Added

- Add `Graph`, a unified interface for arbitrarily nested objects and arrays

## [v0.20.81] - 2023-10-28

### Added

- Sync: add `SyncInvalidEntityException`

### Fixed

- Sync: fix definition builder issue where generic types fail to propagate
  - "generate builder": always add a declared method for parameters and properties that receive a class-wide generic type

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

[v0.21.5]: https://github.com/lkrms/php-util/compare/v0.21.4...v0.21.5
[v0.21.4]: https://github.com/lkrms/php-util/compare/v0.21.3...v0.21.4
[v0.21.3]: https://github.com/lkrms/php-util/compare/v0.21.2...v0.21.3
[v0.21.2]: https://github.com/lkrms/php-util/compare/v0.21.1...v0.21.2
[v0.21.1]: https://github.com/lkrms/php-util/compare/v0.21.0...v0.21.1
[v0.21.0]: https://github.com/lkrms/php-util/compare/v0.20.89...v0.21.0
[v0.20.89]: https://github.com/lkrms/php-util/compare/v0.20.88...v0.20.89
[v0.20.88]: https://github.com/lkrms/php-util/compare/v0.20.87...v0.20.88
[v0.20.87]: https://github.com/lkrms/php-util/compare/v0.20.86...v0.20.87
[v0.20.86]: https://github.com/lkrms/php-util/compare/v0.20.85...v0.20.86
[v0.20.85]: https://github.com/lkrms/php-util/compare/v0.20.84...v0.20.85
[v0.20.84]: https://github.com/lkrms/php-util/compare/v0.20.83...v0.20.84
[v0.20.83]: https://github.com/lkrms/php-util/compare/v0.20.82...v0.20.83
[v0.20.82]: https://github.com/lkrms/php-util/compare/v0.20.81...v0.20.82
[v0.20.81]: https://github.com/lkrms/php-util/compare/v0.20.80...v0.20.81
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
