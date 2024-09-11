# Changelog

Notable changes to `salient/toolkit` are documented in this file.

It is generated from the GitHub release notes of the project by [salient/changelog][].

The format is based on [Keep a Changelog][], and this project adheres to [Semantic Versioning][].

[salient/changelog]: https://github.com/salient-labs/changelog
[Keep a Changelog]: https://keepachangelog.com/en/1.1.0/
[Semantic Versioning]: https://semver.org/spec/v2.0.0.html

## [v0.99.50] - 2024-09-11

### Changed

#### `Contract`

- Simplify `StreamWrapper` and related interfaces

#### `Cache`

- Remove problematic `$maxAge` parameters from cache store methods
- Rename `getAllKeys()` to `getItemKeys()` for consistency
- Rename `CacheStoreInterface` to `CacheInterface`

## [v0.99.49] - 2024-09-10

### Added

#### `Contract`

- Add `StreamWrapper`, an abstraction of the `streamWrapper` prototype class described in the PHP manual

#### `Curler`

- Add `CurlerPageRequest` and allow it to be returned from `CurlerPagerInterface::getFirstRequest()` to minimise query string deserialization between requests
- Add `CurlerPage` methods `getCurrent()` and `getTotal()`, which return `$current` and `$total` values passed to `__construct()`, so progress can be tracked across responses if needed
- Add `AbstractRequestException` and `AbstractResponseException` to API so middleware can extend them

#### `Testing` (new)

- Add `MockPhpStream`
- Add `MockTarget` (from `Console`)

### Changed

#### `Curler`

- **In `CurlerPagerInterface::getPage()`, add `$response` and `$query` parameters to simplify pager code and improve consistency**
- Move `isLastPage()` and `getNextRequest()` from `CurlerPageInterface` to `CurlerPageRequestInterface` and extend the latter from the former
- Replace `isLastPage()` with `hasNextRequest()`

#### `Sli`

- Review `sli` subcommand names

#### `Sync`

- Move `SendHttpRequest` from `Sli` to `Sync`, rename it to `SendHttpSyncProviderRequest`, and refactor for consistency
- Rename `GetSyncEntities` to `GetSyncEntity` for consistency

### Removed

#### `Console`

- Remove `ConsoleInvalidTargetException`

#### `Curler`

- Remove redundant `HttpErrorExceptionInterface::getStatusCode()` and `isNotFoundError()` methods

### Fixed

#### `Curler`

- Fix `Curler` issue where initial query is not passed to pagers
- Fix `Curler` pagination issue where entities are returned with the same keys for each page of data

#### `Sync`

- Fix `SyncEntityProvider` issue where upstream iterator keys are preserved unnecessarily, leading to possible data loss

## [v0.99.48] - 2024-09-06

### Added

#### `Container`

- Add application method `getHarFilename()`

### Changed

#### `Container`

- In application method `exportHar()`:
  - Defer creation of HAR file until first `Curler` request
  - Allow `$uuid` to be given via callback (e.g. to match `Sync::getRunUuid()`)

#### `Curler`

- Add optional `$event` parameter to `CurlerHarRecorder::start()`

### Fixed

#### `Http`

- Fix `OAuth2Client` issue where a stale JWKS may not be refreshed
- Fix `HttpHeaders` issue where `getLines()` returns unsorted headers after the collection is sorted or reversed
- Add and implement `HttpHeadersInterface::canonicalize()` to resolve issue where `HttpHeaders::getLines()` may return headers in an order not compliant with \[RFC7230]

## [v0.99.47] - 2024-09-05

### Changed

#### `Console`

- Adopt "^ " instead of "? " as the default warning message prefix

#### `Curler`

- Rename `CurlerHttpArchiveRecorder` to `CurlerHarRecorder`
- Follow `Location` headers in `Curler` instead of enabling cURL's `CURLOPT_FOLLOWLOCATION` option
  - This allows `CurlerHarRecorder` to be used when following redirects
  - It will also allow redirect behaviour, including caching, to be customised in a future release
- Throw `TooManyRedirectsException` if `MaxRedirects` is exceeded in `Curler`
- Improve cache stability by serializing header values, not `HttpHeaders` instances, when caching responses in `Curler`
- Don't silently remove query and fragment from request URI in `Curler::withRequest()`
  - An exception is now thrown if the given URI has a query or fragment

### Fixed

#### `Curler`

- Fix issue where `Curler` response cache resolves equivalent requests separately if one has an empty path and the other path is `"/"`
- Fix `Curler` issue where HTTP error responses are cached if they are not thrown
- Fix `Curler` issue where request bodies larger than 2MiB are not rewound when they are retried after "429 Too Many Requests"
  - An exception is now thrown if a request body cannot be rewound after redirection or "429 Too Many Requests"
- Fix `Curler` issue where multiple response bodies may be returned as one when a request is retried after "429 Too Many Requests"

## [v0.99.46] - 2024-09-02

### Added

#### `Container`

- Add application method `exportHar()`

#### `Curler`

- **Add `CurlerHttpArchiveRecorder`**
- Add, implement and dispatch `Curler` request and response events
- Add and implement `Curler` exception interfaces
- Add `Curler` method `getFormDataFlags()`

#### `Http`

- Add `Http::replaceQuery()`

### Changed

#### `Container`

- Rename methods:
  - `ContainerInterface::unbindInstance()` -> `removeInstance()`
  - `ApplicationInterface::syncNamespace()` -> `registerSyncNamespace()`
- In `Application`:
  - Use `var/lib/config` and `var/lib/data` instead of `config` and `var/lib` as default config and data directories
  - Use `$_SERVER['REQUEST_TIME_FLOAT']` to calculate elapsed time
  - Throw an exception if `logOutput()` is called multiple times
- Update `ApplicationInterface::registerShutdownReport()` parameters for consistency

#### `Curler`

- Replace `CurlerInterface::getUriWithQuery()` with `replaceQuery()`

#### `Http`

- Build out `jsonSerialize()` methods
- Rename `Http::applyToQuery()` to `mergeQuery()`

### Removed

#### `Curler`

- `CurlerFile`: remove unused `getCurlFile()` method

### Fixed

#### `File`

- In `File::maybeOpen()`, always set `$uri` if null

## [v0.99.45] - 2024-08-28

### Added

#### `Sync`

- Add and implement new exception interfaces:
  - `HeartbeatCheckFailedExceptionInterface`
  - `SyncOperationNotImplementedExceptionInterface`
  - `UnreachableBackendExceptionInterface`

#### `Utility`

- Add `$trim` arguments to `Format::list()` and `Format::array()`

### Changed

#### `Sync`

- Move `Http`- and `Db`-specific classes to their own namespaces
- Rename exception classes for consistency:
  - `SyncFilterPolicyViolationException` -> `FilterPolicyViolationException`
  - `SyncInvalidFilterException` -> `InvalidFilterException`
  - `SyncInvalidFilterSignatureException` -> `InvalidFilterSignatureException`
  - `SyncProviderBackendUnreachableException` -> `UnreachableBackendException`
  - `SyncProviderHeartbeatCheckFailedException` -> `HeartbeatCheckFailedException`

### Removed

#### `Sync`

- Remove unused `SyncInvalidRequestException`

### Fixed

#### `Sync`

- Fix issue where `HttpSyncDefinition` fails to pass payloads applied via `withArgs()` to `READ` or `READ_LIST` operations regardless of the HTTP method they resolve to
- Fix `HttpSyncDefinition` issue where context values are claimed as if they are filters

## [v0.99.44] - 2024-08-26

### Added

- Add PHPStan extension for `Arr::flatten()`

## [v0.99.43] - 2024-08-24

### Fixed

- Fix PHP 8.4 `Implicitly marking parameter as nullable is deprecated` notice

## [v0.99.42] - 2024-08-22

### Added

#### `Utility`

- Add optional `$nullEmpty` parameter to `Arr::trim()` so empty strings can be replaced with `null` instead of being removed

### Changed

#### `Cli`

- Fall back to defaults when `_man` or `_md` commands receive empty arguments

### Fixed

#### `Cli`

- Fix issue where long synopses cause Markdown usage information with man page extensions to be generated with unwanted line breaks

#### `Console`

- Fix inconsistent newline handling in `ConsoleFormatter`

## [v0.99.41] - 2024-08-14

### Added

#### `Http`

- Add `Http::applyToQuery()` for simple manipulation of query strings in URIs

### Changed

#### `Core`

- Move `Graph` from `Iterator` to `Core` and refactor
  - Allow `Graph` to be extended
  - Rename `Graph::inner()` to `getValue()`
  - Don't create missing properties or array keys on `Graph::offsetGet()` unless enabled via new constructor parameters
  - Add and implement `GraphInterface`

### Removed

#### `Core`

- Remove `Graph::with()` and `Graph::from()`

## [v0.99.40] - 2024-08-07

### Added

#### `Sli`

- Add `getDateProperties()` when generated sync entities have date properties

### Changed

#### `Console`

- Review order of `Console::message()` and `Console::messageOnce()` parameters for consistency

### Removed

#### `Core`

- Remove unused method `ProviderContextAwareInterface::requireContext()`

## [v0.99.39] - 2024-08-07

### Added

#### `Cache`

- Add and implement `CacheStoreInterface::close()`

### Fixed

#### `Http`

- Fix cache handling bugs in `OAuth2Client`

## [v0.99.38] - 2024-08-06

### Fixed

#### `Contract`

- Remove support for `psr/simple-cache=^3` to fix incompatible return type bug

#### `Sync`

- Update `pipelineFrom()` and `pipelineTo()` return types in `AbstractSyncProvider`

## [v0.99.37] - 2024-08-06

### Added

#### `Utility`

- Add `Arr::toStrings()`
- Add `File::mkdir()`
- Add `File::touch()`
- Add `Sys::getTempDir()`

### Changed

#### `Utility`

- Rearrange parameters of `Arr::get()`, `Arr::has()`, `Arr::keyOf()` and `Arr::with()` for consistency
- Require at least two arguments in `Arr::same()` and `Arr::sameValues()`
- Trim whitespace by default in `Arr::implode()`
- Rename `File` methods:
  - `sanitiseDir()` -> `getCleanDir()`
  - `closestExisting()` -> `closestPath()`
  - `relativeToParent()` -> `getRelativePath()`

### Removed

#### `Utility`

- Remove `File::getStablePath()`
- Remove `File::isPharUri()`

### Fixed

- Fix subtree split package dependency issues

## [v0.99.36] - 2024-08-02

### Fixed

#### `Console`

- Fix issue where progress spinner does not change state

## [v0.99.35] - 2024-08-02

### Added

#### `Contract`

- Add and implement/adopt:
  - `BuilderInterface`
  - `MethodNotImplementedException` and other exception interfaces
  - `SyncErrorInterface`
  - `SyncErrorCollectionInterface`
  - `DeferredEntityInterface`
  - `DeferredRelationshipInterface`
- Add `Chainable::withEach()` and implement in `HasChainableMethods`

#### `Http`

- Add `Http::isRequestMethod()`

#### `Utility`

- Add `Reflect` methods:
  - `getConstants()`
  - `getConstantsByValue()`
  - `hasConstantWithValue()`
  - `getConstantName()`

### Changed

#### `Container`

- Move `ServiceLifetime` to `Contract`
- Rename `Container` exceptions

#### `Contract`

- Convert dictionaries and enumerations to interfaces

### Removed

#### `Contract`

- Remove `HttpRequestMethodGroup`

### Fixed

#### `Console`

- Fix `Console::logProgress()` spinner state bug

## [v0.99.34] - 2024-07-29

### Changed

- Move `sli` command to `salient/sli` package
- Provide `psr/http-client-implementation`

### Fixed

- Fix reference to invalid package `salient/utility` from `salient/container`

## [v0.99.33] - 2024-07-29

### Added

#### `Cli`

- Add `CliHelpStyleInterface`

#### `Console`

- Add `ConsoleFormatterInterface`
- Add `ConsoleMessageFormatInterface`
- Add `ConsoleMessageAttributesInterface`
- Add `ConsoleTagAttributesInterface`

### Changed

#### `Console`

- Rename `ConsoleFormatter::formatTags()` -> `format()`
- Move `ConsoleFormatInterface` to `Contract`

## [v0.99.32] - 2024-07-20

### Changed

#### `Utility`

- Rename `Arr::keyOffset()` -> `offsetOfKey()` for consistency
- Rename `Arr::listWrap()` -> `wrapList()` for consistency
- Rename `Inflect::formatWithSingularZero()` -> `formatWithZeroAsOne()`
- Rename `Reflect::getCallableParamClassNames()` -> `getAcceptedTypes()` and add optional `$skipBuiltins` parameter
- Rename `Reflect::getAllTypes()` -> `getTypes()`
- Rename `Reflect::getAllTypeNames()` -> `getTypeNames()`
- Move PHPDoc-related methods from `Reflect` to `PHPDocUtility`

### Removed

#### `Utility`

- Remove `Arr::isListOf*()` methods
- Remove `Reflect::isMethodInClass()` from public API
- Remove `Regex::BEFORE_UNESCAPED`
- Remove `Sys::getMemoryUsage()`, `Sys::getPeakMemoryUsage()`

## [v0.99.31] - 2024-07-05

### Added

#### `Cli`

- Add `{{subcommand}}` to help message substitutions

#### `Console`

- Add `getEol()` to `ConsoleTargetStreamInterface`

#### `Iterator`

- Add `IterableIterator::fromValues()` to iterate over values while discarding keys

#### `Sync`

- Add `SyncPipelineArgument` and use it to unify pipeline signatures
- In `GetSyncEntities` command:
  - Add `--field` option
  - Allow entity and provider to be given as fully-qualified names when no providers that service entities are registered

#### `Utility`

- Add `Arr::pluck()`
- Add `$index` parameter to `File::writeCsv()` callback
- Add optional `$eol` parameter to `Json::prettyPrint()`

### Changed

#### `Cli`

- Rename `nameParts()` -> `getNameParts()` for consistency
- Widen `getOptionList()` return type to `iterable`

#### `Container`

- Remove template from `HasContainer` interface

#### `Core`

- Rename `Nameable` -> `HasName`, `name()` -> `getName()`
- Rename `Describable` -> `HasDescription`, `description()` -> `getDescription()`
- Rename `Identifiable` -> `HasId`, `id()` -> `getId()`
- Rename `dateFormatter()` -> `getDateFormatter()`
- Move `Pipeline` interfaces to `Core` namespace
- Only allow pipeline method `through()` to add one pipe at a time

#### `Sync`

- Rename methods for consistency:
  - `AbstractProvider::getDateFormatter()` -> `createDateFormatter()`
  - `SyncProviderInterface::store()` -> `getStore()`
  - `SyncStoreEventInterface::store()` -> `getStore()`
  - `SyncEntityInterface::canonicalId()` -> `getCanonicalId()`
  - `SyncEntityInterface::uri()` -> `getUri()`
  - `DeferredEntity::uri()` -> `getUri()`
- In `HttpSyncProvider`:
  - Replace `buildCurler()` with `filterCurler()`
  - Replace `buildHttpDefinition()` with `getHttpDefinition()` and `builderFor()`
  - Remove nullability from most `$path` parameters
  - Don't override `createDateFormatter()`
- Replace `DbSyncProvider::buildDbDefinition()` with `getDbDefinition()` and `builderFor()`
- In definition interfaces and classes:
  - Rename `getSyncOperationClosure()` -> `getOperationClosure()`
  - Rename `withReadFromReadList()` -> `withReadFromList()`
  - Refactor `HttpSyncDefinition` for clarity and to improve type safety
  - Throw an exception instead of returning `null` from `getFallbackClosure()` when the given operation is not supported
- Move event interfaces to their own namespace

### Removed

#### `Sync`

- Remove `bindOverride()` from definition classes
- Remove `SyncProviderInterface::setProviderId()`
- Remove `SyncOperationGroup`

### Fixed

#### `Cli`

- Fix issue where help for positional `ONE_OF` options may not include option name

#### `Sync`

- Fix issue where some `HttpSyncDefinition` changes applied via callback are ignored

### Security

#### `Utility`

- Change default value of `Get::filter()` parameter `$discardInvalid` so invalid key-value pairs trigger an exception by default

## [v0.99.30] - 2024-06-20

### Added

#### `Utility`

- Add `Str::title()`

### Changed

#### `Utility`

- Rename `Package` methods:
  - `reference()` -> `ref()`
  - `packageReference()` -> `getPackageRef()`
  - `packageVersion()` -> `getPackageVersion()`
  - `packagePath()` -> `getPackagePath()`
  - `classPath()` -> `getClassPath()`
  - `namespacePath()` -> `getNamespacePath()`
- Rename `Str` methods:
  - Rename `wrap()` to `enclose()`
  - Rename `wordwrap()` to `wrap()`
- Don't accept or return `null` in `Str::trimNativeEol()`, `Str::eolToNative()`, `Str::eolFromNative()`
- In `Str::normalise()`, only replace `"&"` with `" and "` when it is the only ampersand between two alphanumeric characters

### Removed

#### `Utility`

- Remove `Get::notNull()`

## [v0.99.29] - 2024-06-18

### Added

- PHPStan: Add `Arr::extend()` return type extension

## [v0.99.28] - 2024-06-15

### Added

#### `Core`

- In `AbstractStore`:
  - Add support for temporary databases created on the filesystem
  - Add `isTemporary()` to check if the store is backed by a temporary or in-memory database
  - Add and adopt protected `SQLite3::prepare()` and `SQLite3Stmt::execute()` wrapper methods `prepare()` and `execute()`
  - Improve support for cloning (e.g. by `CacheStore::asOfNow()`) by tracking state in an object shared between instances
  - Add `detach()` and `detachDb()` methods so stores can be closed without their clones also closing
  - Add `hasTransaction()`

### Changed

#### `Core`

- Don't allow `AbstractStore` subclasses to be cloned unless they override `__clone()`

#### `Cache`

- Improve concurrency in `CacheStore` by starting a SQLite transaction when `CacheStore::asOfNow()` returns a copy of the store, and committing it when the copy goes out of scope or is explicitly closed

## [v0.99.27] - 2024-06-13

### Fixed

#### `Console`

- Use message prefixes with better cross-platform support

#### `Utility`

- Remove visible codepoint ranges from `INVISIBLE_CHAR` regex

## [v0.99.26] - 2024-06-12

### Added

#### `Console`

- Add `Console::escape()`

#### `Core`

- Add `ErrorHandler::handleExitSignal()` so signal handlers can report the exit status of the running script before it terminates on `SIGTERM`, `SIGINT` or `SIGHUP`

### Changed

#### `Console`

- Preserve output from `Console::logProgress()` when exiting on error

#### `Core`

- In `ErrorHandler::handleException()`, set shutdown flags and resolve exit status before calling `Console::exception()` so console output targets can respond appropriately during shutdown

#### `Utility`

- In `Sys::handleExitSignals()`, report exit on `SIGTERM`, `SIGINT` or `SIGHUP` to `Err::handleExitSignal()`

### Fixed

#### `Console`

- Fix `ConsoleFormatter::formatDiff()` issue where an exception is thrown when a unified diff contains a line that starts with `-++`

## [v0.99.25] - 2024-06-11

### Added

#### `Console`

- Add `ConsoleWriterInterface` and adopt where appropriate
- Add a spinner to `Console::logProgress()` output
- Add `ConsoleLogger` to finalise PSR-3 support
- Add `Console::getLogger()`

### Changed

#### `Console`

- **Update message decorations and formatting**
- Optionally suppress error count and associated formatting in `Console::summary()`
- Update `Console::group()` API
- Rename `CONSOLE_TARGET` environment variable to `console_target`
- Rename `Console` methods for consistency:
  - `getErrors()` -> `getErrorCount()`
  - `getWarnings()` -> `getWarningCount()`
  - `maybeClearLine()` -> `clearProgress()`
  - `out()` -> `printOut()`
  - `tty()` -> `printTty()`
  - `stdout()` -> `printStdout()`
  - `stderr()` -> `printStderr()`
- Optionally filter targets by level and type in `Console::getTargets()`
- Always replace existing `STDOUT`/`STDERR` targets

#### `Core`

- **Move `Salient\Core\Utility` namespace to `Salient\Utility`**
- **Move `Regex` values to `Pcre` and rename `Pcre` to `Regex`**
- Move `CopyFlag` values to `Get` and rename for clarity as needed
- Move `EnvFlag` values to `Env` and rename for clarity as needed
- Rename `Env` methods:
  - `load()` -> `loadFiles()`
  - `environment()` -> `getEnvironment()`
  - `home()` -> `getHomeDir()`
  - `dryRun()` -> `getDryRun()` and `setDryRun()`
  - `debug()` -> `getDebug()` and `setDebug()`
  - `flag()` -> `getFlag()` and `setFlag()`
- Do not print console messages from `Env`
- Move `AbstractUtility` and `PackageDataReceivedEvent` to `Utility` namespace
- Move exceptions to `Utility` namespace and refactor as needed

### Removed

#### `Console`

- Remove unused/internal `Console` methods:
  - `deregisterAllTargets()`
  - `maybeRegisterStdioTargets()`
  - `registerLogTarget()`
- Remove `$replace` parameters from `Console` methods

#### `Core`

- Remove `Env::getClass()`
- Remove `Env::isLocaleUtf8()`
- Remove `JsonEncodeFlag` and `JsonDecodeFlag` after replacing references to them with equivalent `JSON_*` values
- Remove exceptions with the same name as their native parent:
  - `BadMethodCallException`
  - `InvalidArgumentException`
  - `LogicException`
  - `OutOfRangeException`
  - `RuntimeException`
  - `UnexpectedValueException`

### Fixed

#### `Core`

- Fix `DateParser::parse()` issue where empty strings are interpreted as `'now'`
- Fix `DateParser::parse()` issue where `$timezone` is not applied after parsing
- Fix incorrect example in `Env` documentation
- In `Format::value()`, fall back to `Get::type()` if encoding fails

#### `sli`

- In `generate` commands, detect (some) constants to fix issue where `JSON_*` constants in PHPDoc types are treated as class names

## [v0.99.24] - 2024-06-05

### Added

#### `Collection`

- Add collection methods `has()` and `get()` for accessing items by key (existing methods with the same name have been renamed as below)
- Add collection method `map()` (analogous to `array_map()`)
- Add `handleItemsReplaced()` method to `CollectionTrait`

#### `Core`

- Add `Reflect::normaliseType()` to allow inspection of declared types without flattening intersection types

#### `Sync`

- Add `AbstractSyncEntity::getParentSerializeRules()` to simplify serialization rule inheritance

### Changed

#### `Collection`

- Rename collection methods `has()` and `get()` to more consistent `hasValue()` and `firstOf()`
- In `ReadableCollectionTrait`, apply `filterItems()` even when merging collections of the same class

#### `Core`

- In `Reflect`:
  - Normalise nullable types (`?int`) to equivalent unions (`int|null`)
  - Rename `getFirstCallbackParameterClassNames()` to `getCallableParamClassNames()` and allow `$param` to be given, return intersection types as nested arrays
  - Rename `getMethodPrototypeClass()` to `getPrototypeClass()`
- Ignore intersection types in `EventDispatcher::listen()`

#### `Sync`

- In `SyncErrorCollection`:
  - Rename `toSummary()` to `getSummary()`
  - Rename `toString()` to `getSummaryText()` and simplify
  - Add `reportErrors()` from `SyncStore::reportErrors()`
  - Use `CollectionTrait::handleItemsReplaced()` to maintain error and warning counts
- Do not call closures provided via serialization rules to replace `null`
- When rule recursion is enabled in `SyncSerializeRules`:
  - Apply inherited rules to inherited entity classes recursively
  - Only apply rules for entities seen at each path (i.e. don't apply path-based rules for `UserSubClass` to nested instances of `User`)

#### `PHPStan`

- Improve `GetCoalesceRule` recommendations

### Removed

- Remove `SyncStoreInterface::reportErrors()` (moved to `SyncErrorCollection`)

### Fixed

- Fix `SyncSerializeRules` regression where rules are not applied recursively at the point of recursion (i.e. to instances of the entity class being serialized)

## [v0.99.23] - 2024-06-01

### Added

#### `Core`

- Add `ErrorHandler` methods `isShuttingDown()`, `isShuttingDownOnError()` and `getExitStatus()`
  - Destructors can use these via the `Err` facade to determine the script's exit status during shutdown and respond accordingly
- Add `Arr::setIf()`
- Add `getContainer()` to builders

#### `Curler`

- Add `CurlerInterface::withRequest()` so middleware can use `Curler` to inspect arbitrary requests

#### `Sync`

- Add `SyncStore::getBinaryRunUuid()` and remove `$binary` parameter from `getRunUuid()`
- Add `SyncStore::getEntityId()`
- Add `SyncStoreInterface` and adopt where appropriate

#### `PHPStan`

- Add PHPStan extensions:
  - `GetCoalesceRule` to report unnecessary use of `Get::coalesce()`
  - `ArrWhereNotEmptyMethodReturnTypeExtension` to resolve return type of `Arr::whereNotEmpty()`, including for constant arrays
  - `ArrWhereNotNullMethodReturnTypeExtension` to similarly resolve return type of `Arr::whereNotNull()`

#### `sli`

- Add `--no-declare` option to `sli generate builder`

### Changed

#### `Cache`

- **Automatically remove expired items from `CacheStore`**
- Adopt `CacheStoreInterface` instead of `CacheStore` where appropriate

#### `Container`

- Resolve internal interfaces to internal implementations by default, e.g. resolve `CacheStoreInterface` to `CacheStore` if it hasn't been bound to something else
- Move `RequiresContainer` to `Container` namespace
- In `RequiresContainer::requireContainer()`, fall back to the global container if it exists, otherwise create a standalone container (unless `$createGlobalContainer = true`)

#### `Core`

- Rename builder methods for consistency and clarity:
  - `build()` -> `create()`
  - `go()` -> `build()`
- Move `Http` utility class to `Http` namespace
- Move `Get` methods to new `FormData` class in `Http` namespace:
  - `query()` -> `getQuery()`
  - `formData()` -> `getValues()`
  - `data()` -> `getData()`
- Rename `QueryFlag` to `FormDataFlag` and move to `Http` namespace
- Rename `Arr::same()` to `Arr::sameValues()` and add `Arr::same()`, which also checks keys (as one would expect)
- Simplify `Sys::handleExitSignals()`
- Move `Char` constants to `Str`
- Move `SortFlag` constants to `Arr`
- Rename `AbstractStore::requireUpsert()` to `assertCanUpsert()` and return `void`

#### `Curler`

- Surface middleware messages via `Curler::getLastRequest()` and `Curler::getLastResponse()`
- Pass `$curler` to `Curler` cache key callbacks
- Add signature to `$next` closure in `Curler` middleware annotations
- Allow `Curler`'s user agent header to be suppressed
- Rename `CurlerInterface::withQueryFlags()` to `CurlerInterface::withFormDataFlags()`
- Rename `CurlerBuilder::queryFlags()` to `CurlerBuilder::formDataFlags()`

#### `Sync`

- Revert serialization changes in @9344acb3, where containers are propagated via serialization rules, in favour of passing an optional entity store to serialize operations
  - This allows serialization of possibly-detached entities--that may belong to a namespace registered with a store they can't locate--without making serialization rules container-dependent
- In `SyncEntityInterface`:
  - Rename `defaultProvider()` -> `getDefaultProvider()`
  - Make `$container` a required parameter for `getDefaultProvider()` and `withDefaultProvider()`
  - Remove `$container` parameter from `getSerializeRules()`
  - Rename `plural()` -> `getPlural()` and make return type nullable
  - Add `$store` parameter to `toArray()` and `toArrayWith()`
  - In `toArrayWith()`, don't allow `$rules` to be an unresolved builder
  - Pass `$store` instead of `$container` to `toLink()` and `uri()`
- Remove `static` modifier from `SyncClassResolverInterface` methods
- Rename `SyncStore` methods:
  - `hasRunId()` -> `runHasStarted()`
  - `provider()` -> `registerProvider()`
  - `getProviderHash()` -> `getProviderSignature()`
  - `entityType()` -> `registerEntity()`
  - `namespace()` -> `registerNamespace()` and require `$resolver` to be a `SyncClassResolverInterface` instance
  - `getEntityTypeUri()` -> `getEntityUri()`
  - `getEntityTypeNamespace()` -> `getEntityPrefix()`
  - `getNamespaceResolver()` -> `getClassResolver()` and return `SyncClassResolverInterface|null`
  - `entity()` -> `setEntity()`
  - `deferredEntity()` -> `deferEntity()`
  - `deferredRelationship()` -> `deferRelationship()`
  - `resolveDeferred()` -> `resolveDeferrals()` and add `$providerId`, remove `$return`, always return resolved entities
  - `checkHeartbeats()` -> `checkProviderHeartbeats()`
  - `error()` -> `recordError()`
- Add `$forEntityProperty` parameter to `SyncStore::resolveDeferredRelationships()`
- Automatically detect exit status during `SyncStore` shutdown
- In `SyncSerializeRules`:
  - Don't flatten rules until they are compiled, in case rules for entities with different normalisers are merged
  - Normalise new key names during compilation
- Add/rename/replace in `SyncSerializeRules` and related interfaces:
  - `getEntity()`
  - `apply()` -> `merge()`
  - `getRemoveFrom()` -> `getRemovableKeys()`
  - `getReplaceIn()` -> `getReplaceableKeys()`
  - `withDateFormatter()`
  - `withDetectRecursion()`
  - `getRecurseRules()`
  - `withRecurseRules()`
  - `getForSyncStore()` and remove `getFlags()`
  - `withForSyncStore()`
  - `getRemoveCanonicalId()` -> `getIncludeCanonicalId()`
  - `withRemoveCanonicalId()` -> `withIncludeCanonicalId()`
- In `SyncProviderInterface::with()`, do not accept `ContainerInterface` as a `$context`

### Removed

- Remove `getApp()` methods in favour of `getContainer()`
- Remove container method `instanceIf()` to address unexpected outcome when existing bindings are not shared instances
- Remove `Arr::upperFirst()`
- Remove `HasProvider::requireProvider()`
- Remove `AbstractStore::isTransactionOpen()`
- Remove `AbstractSyncEntity::store()`
- Remove `AbstractSyncProvider::buildSerializeRules()`
- Remove `SyncEntityLinkType::INTERNAL`

### Fixed

- Fix `Arr::rename()` bug when renaming a key with a `null` value
- Remove `of object` from container method templates to fix static analysis errors when binding or resolving interfaces
- Fix issue where serialization rules applied to date and time values trigger an exception
- Fix issue where nested value paths may be incorrect during serialization, preventing rules from applying correctly

### Security

- Add "unstable" HTTP header group and use it to filter headers for inclusion in `Curler`'s default cache key instead of "sensitive" headers, which had non-obvious security implications by default

## [v0.99.22] - 2024-05-21

### Added

- Implement PSR-16 caching interface in `CacheStore`
  - In `set()`, rename `$expires` parameter to `$ttl`
  - **Remove values from the cache when `$ttl` is an integer less than or equal to `0`**
  - **Add `$default` parameter to `get()`, `getInstanceOf()`, etc.**
  - Rename `deleteAll()` -> `clear()`
  - Rename `flush()` -> `clearExpired()` for consistency
  - Adopt PSR-16 return types (`bool` instead of `$this`)
  - Add `setMultiple()`, `getMultiple()`, `deleteMultiple()`
  - Add `CacheStoreInterface`
- Add `PHPDoc::getTemplates()`

### Changed

- Improve `PHPDoc` tag inheritance
- Remove leading `$` from `PHPDoc` `@var` names

### Removed

- Remove unused `CacheStore::maybeGet()` method

## [v0.99.21] - 2024-05-20

### Added

- Add `Str::PRESERVE_QUOTED`

### Changed

- Allow `null` to be passed to all `Format` methods
- Allow the current year to be passed to `Format::date()` and `Format::dateRange()`
- In `Format::dateRange()`:
  - Do not report time when the time of both dates is `00:00:00`, even if they are in different timezones
  - Report time with both timezones when daylight saving time is active in one date but not the other
- In `Format::bytes()`:
  - Add option to use decimal units
  - Change default precision from `0` to `3`
  - Improve output for values near binary unit boundaries
  - Suppress redundant decimal output when unit is 'B'
- Move DocBlock-related regular expressions from `Regex` to `PHPDocRegex`
- Move `normaliseType()` from `PHPDocTag` to `PHPDoc`
- Move `PHPDocTag` and related classes to `PHPDoc\Tag`, then rename and make them immutable
- Allow PHPDoc `@param` tags to indicate they are passed by reference
- Improve parsing, e.g. when invalid whitespace is added between tokens
- Fail with an exception when a DocBlock cannot be parsed

### Removed

- Remove `$legacyNullable` support from `PHPDoc` classes

### Fixed

- Fix rounding bugs in `Format::bytes()`
- Fix issue where `Test::isNumericKey()` ignores trailing newlines
- Fix issue where `PHPDoc::$Summary` may not be `null` when a DocBlock has no summary
- Fix issue where `/***/` is not recognised as an invalid DocBlock

## [v0.99.20] - 2024-05-14

### Added

- Add `--collapse` option to `sli generate` commands

### Changed

- In `CliOption`, validate positional option names, discarding `long` and `short` if given
- Rename methods for clarity:
  - `CliOption::getValueName()` -> `CliOption::getValueNameWords()`
  - `CliOption::formatValueName()` -> `CliOption::getValueName()`
- Improve `CliCommand` annotations for downstream static analysis

### Fixed

- Fix `Cli` issue where escapes in help message content are not always honoured
- Fix `ConsoleFormatter::formatTags()` issue where escapes are not reinstated correctly
- In `sli generate` commands, only use `Differ` if `sebastian/diff` is installed

## [v0.99.19] - 2024-05-13

### Added

- Add `CliCommand::checkOptionValues()`

## [v0.99.18] - 2024-05-03

### Fixed

- Fix return type regression in `CollectionInterface::find()`

## [v0.99.17] - 2024-05-03

### Added

- Add `Process` methods `pipeInput()`, `setCwd()`, `setEnv()`, `setTimeout ()`, `disableOutputCollection()`, `enableOutputCollection()`, `isTerminatedBySignal()`
- Add `Sys::isProcessRunning()`

### Changed

- In `Process` and `Sys::handleExitSignals()`, use exit status `128 + <signal_number>` when processes are terminated by signal
- Review `Process`:
  - Update constructor and `withShellCommand()` parameters
  - Replace `null` input with an empty stream (`STDIN` must now be passed explicitly via `pipeInput()`)
  - Improve robustness and precision of timeout handling and process termination
  - In `runWithoutFail()`, throw `ProcessFailedException` when a process returns a non-zero exit status
  - Throw `ProcessTerminatedBySignalException` when a process monitored by `wait()` is terminated by a signal that isn't a `SIGTERM` or `SIGKILL` sent after calling `stop()`
  - Throw `LogicException` instead of `ProcessException` where appropriate
  - Build out `getStats()` metrics
- Create key-value pairs for `CollectionInterface::CALLBACK_USE_BOTH` (`[<key>, <value>]` instead of `[<key> => <value>]`)
- Optionally return key from `CollectionInterface::find()`
- Throw PSR-18 compliant exceptions from `Curler::sendRequest()`

### Removed

- Remove unused `Sys::sqliteHasUpsert()`

### Fixed

- Fix `Process` output collection bugs

## [v0.99.16] - 2024-04-30

### Added

- Add `CurlerInterface::withUri()`
- Add `getHeaderValues()` and `get{First,Last,One}HeaderLine()` to `CurlerInterface` and `HttpMessageInterface`
- Add `LinkPager`

### Changed

- Adopt "PHPDoc" nomenclature instead of "PhpDoc"

### Fixed

- Fix invalid `Inflect::format()` syntax in `Curler`

## [v0.99.15] - 2024-04-29

This release includes a backward-incompatible rewrite of `Curler`, the toolkit's HTTP client, with PSR-7 and PSR-18 support, stackable middleware and many other improvements.

`Curler`-specific changes include:

- Implement `CurlerInterface` and remove magic properties
- Update `CurlerBuilder` methods
  - **`baseUrl()` -> `uri()`**
    - _now accepts `UriInterface|Stringable|string`_
  - **`cacheResponse()` -> `cacheResponses()`**
  - **`cachePostResponse()` -> `cachePostResponses()`**
  - **`expiry()` -> `cacheLifetime()`**
    - _not nullable_
    - _`-1` now means "suppress response caching"_
    - _`cacheResponses()` must also be called_
  - **`flush()` -> `refreshCache()`**
  - **`responseCacheKeyCallback()` -> `cacheKeyCallback()`**
    - _callback now receives `HttpRequestInterface` and returns `string[]|string`_
  - **~~`responseCallback()`~~**
    - _use `middleware()` instead_
  - **`connectTimeout()` -> `timeout()`**
  - **~~`timeout()`~~**
  - **`cookieCacheKey()` -> `cookiesCacheKey()`**
  - **`preserveKeys()` -> `queryFlags()`**
    - _now accepts bitmask of `QueryFlag::*`_
  - **`objectAsArray()` -> `jsonDecodeFlags()`**
    - _now accepts bitmask of `JsonDecodeFlag::*`_
- Refactor pagination-related interfaces and classes:
  - `ICurlerPager` -> `CurlerPagerInterface`
  - `ICurlerPage` -> `CurlerPageInterface`
  - `CurlerPage`
  - `ODataPager`
  - `QueryPager`
- Refactor exceptions:
  - `CurlerException` -> `AbstractCurlerException`
  - `CurlerCurlErrorException` -> `CurlErrorException`
  - `CurlerHttpErrorException` -> `HttpErrorException`
- Remove:
  - `CurlerProperty` (obsolete)
  - `CurlerPageBuilder` (unnecessary)
  - `CurlerInvalidResponseException` (obsolete)
  - `CurlerUnexpectedResponseException` (obsolete)

---

### Added

- Add and implement `HttpHeadersInterface::getHeaderValues()`
- Add `HttpHeaders` methods:
  - `from()` (static, accepts `MessageInterface|Arrayable|iterable|string`)
  - `getContentLength()`
  - `getMultipartBoundary()`
  - `getPreferences()`
  - `getRetryAfter()`
  - `mergePreferences()`
- Add and implement `HttpMessageInterface::fromPsr7()`
- Add and implement `HttpMultipartStreamPartInterface::withName()`
- Add `HttpMultipartStreamPart::fromFile()`
- Add `HttpStream::fromData()`
- Add `StreamEncapsulationException` and throw it when multipart data cannot be JSON-encoded
- Add `HttpRequestHandlerInterface` (client-side equivalent of PSR-15 `MiddlewareInterface`)
- Add `CurlerMiddlewareInterface`
- Add `HasHttpHeaders` trait
- Add `Get::data()` and `Get::formData()`
- Add `Http` methods:
  - `getDate()`
  - `getParameters()`
  - `mergeParameters()`
  - `getProduct()`
  - `unquoteString()`
- Add `Process` methods needed for unit testing
  - Allow processes to be monitored via `poll()` and stopped via `stop()`
  - Allow `Process` output to be read incrementally via `getNewOutput()` and discarded via `clearOutput()`
  - Only remove trailing newlines from `Process` output retrieved via `getText()` and `getNewText()`
  - Return `Process` statistics via `getStats()` (only `spawn_us` initially)
- Add and adopt `LogicException` and `OutOfRangeException`

### Changed

- Extend `HttpMultipartStreamPart` from `CurlerFile` and refactor
- Extend `Stringable` from `HttpHeadersInterface`
- In `HttpHeadersInterface::getLines()`, add support for non-standard empty header syntax (e.g. libcurl's) via optional `$emptyFormat` parameter
- In `HttpHeaders` methods `getFirstHeaderLine()`, `getLastHeaderLine()` and `getOneHeaderLine()`, return the requested value after combining header values and splitting the result
- Rename `Http` classes and interfaces for consistency
- Review HTTP message constructor signatures
- Accept HTTP protocol versions other than `1.0` and `1.1`, including single-digit variants
- Don't set `Content-Length` when creating HTTP message payloads
- Allow `HttpServer` callback to return `ResponseInterface` instead of `HttpResponseInterface`
- Rename `Http::getQuotedString()` to `Http::maybeQuoteString()` and suppress quoting if the string is a valid HTTP token
- Improve nested object handling in `Get::query()`
  - Apply an optional callback to objects other than `DateTimeInterface` instances
  - Skip values for which the callback returns `null`
  - Resolve `Arrayable` and `Traversable` objects to lists
  - Convert `JsonSerializable` and `Jsonable` objects to JSON and decode
  - Convert other objects to arrays that map public property names to values
  - Cast `Stringable` objects with no public properties to `string`
- Allow `Process` output collection to be disabled
- Allow `Process` timeout to be given as a `float`
- Rename `Str::splitOutsideBrackets()` to `Str::splitDelimited()` and add optional support for preservation of double- and single-quoted substrings (for robust HTTP header value splitting)
- Review `Str::split*()` default values and signatures to better reflect prevailing usage
- Make `PipeInterface` invokable for consistency with `HttpRequestHandlerInterface`
- In `ExceptionInterface`, rename `getDetail()` to `getMetadata()` and relax return type

### Removed

- Remove unnecessary `HttpProtocolVersion` enumeration
- Remove problematic `HttpMessageInterface::withContentLength()`
- Remove inconsistently applied `$preserveKeys` parameter from `Get::array()`

### Fixed

- Remove return type from `__toString()` in the `Stringable` polyfill for better compatibility with the native class, which doesn't require an explicit return type because `__toString()` gets one internally
- Fix issue where `ExceptionTrait::withExitStatus()` is unusable because exceptions cannot be cloned
- Fix issue where `MultipleErrorExceptionTrait` doesn't handle empty messages correctly
- Annotate `HasBuilder` to satisfy static analysis in non-final classes

## [v0.99.14] - 2024-04-11

### Added

- Add optional `$withResourceUsage` parameter to `Console::summary()`
- Add optional `$delete` parameter to `File::pruneDir()`
- Add `Arr::set()`, `Arr::unset()`, `Arr::upperFirst()`
- Add `File::checkEof()`, `File::getLines()`, `File::isStream()`, `File::maybeOpen()`, `File::maybeWrite()`, `File::readAll()`, `File::writeAll()`
- Add `Get::closure()`
- Add `HasImmutableProperties::withoutProperty()`
- Add `Regex::INVISIBLE_CHAR`
- Add `Test::isAsciiString()`
- Add `HttpHeaders` methods `get{First,Last,One}HeaderLine()`, `hasLastLine()`
- Add `HttpMessage::getHttpPayload()`, `HttpMessage::__toString()`
- Add `HttpStream::copyToStream()`, `HttpStream::copyToString()` after renaming `Stream`
- Add `HttpMultipartStream`
- Add `UploadedFile` (PSR-7 implementation)
- Add `Uri::isAuthorityForm()`
- Add `Http::getQuotedString()` and `Http::escapeQuotedString()` (new class)

### Changed

- Replace `HttpResponse` and `HttpServerRequest` with PSR-7 implementations
- In `Uri`:
  - Disable strict URI parsing by default
  - Don't normalise URIs implicitly
  - Optionally replace empty paths with "/" in HTTP URIs
  - Optionally collapse multiple slashes in URIs
  - Make `Uri::parse()` fully compatible with `parse_url()`
- In `HttpMessage` and `HttpHeaders`, implement `JsonSerializable` and scaffold HAR-compliant output from `jsonSerialize()`
- Refactor `HttpServer` for API consistency and more robust request handling
- Rename `Stream` to `HttpStream`
- Don't cache stream size in `HttpStream`
- Don't rewind or truncate streams in `File::copy()`
- Remove optional recursion from `File::deleteDir()`
- Move `File::guessIndentation()` to `Indentation::from()`
- Rename `File` methods:
  - `existing()` -> `closestExisting()`
  - `readCsv()` -> `getCsv()`
  - `getCwd()` -> `getcwd()`
  - `getSeekable()` -> `getSeekableStream()`
  - `isPhp()` -> `hasPhp()`
  - `creatable()` -> `isCreatable()`
  - `isSeekable()` -> `isSeekableStream()`
  - `resolve()` -> `resolvePath()`
  - `dir()` -> `sanitiseDir()`
  - `putContents()` -> `writeContents()`
  - `fputcsv()` -> `writeCsvLine()`
- Rename `Test` methods:
  - `isBoolValue()` -> `isBoolean()`
  - `isIntValue()` -> `isInteger()`
  - `isFloatValue()` -> `isFloat()`
  - `isPhpReservedWord()` -> `isBuiltinType()`
- In `Get::code()`:
  - Add `$constants` parameter that maps substrings to constant identifiers
  - Do not escape CR or LF in multiline mode
  - Do not escape UTF-8 leading or continuation bytes
  - Always escape control characters
  - Escape blank/ignorable characters
  - Remove unnecessary backslashes
  - In arrays with string and integer keys, suppress numeric keys if they are numbered consecutively from `0`
- Accept `iterable` in `Arr::toIndex()` and `Arr::toMap()`
- Remove `Arr::trimAndImplode()` in favour of `Arr::implode()` with optional `$characters`
- In `EventDispatcher`, reject calls to methods other than `dispatch()` when a listener provider is given
- Move `Curler::mimeTypeIs()` to new `Http` utility class and rename to `mediaTypeIs()`
- In `Http::mediaTypeIs()`, support more suffixes (e.g. `+xml`) and improve standards compliance
- Rename `Str::splitAndTrim()` to `Str::split()`
- Merge `Str::splitAndTrimOutsideBrackets()` into `Str::splitOutsideBrackets()`
- In `PhpDoc`, add support for closure templates and recognise `@template` syntax `as <type>` in addition to `of <type>`
- Improve unified diff formatting

### Removed

- Remove `$lastValueOnly` parameter from `HttpHeaders::getHeaderLine()`

### Fixed

- Fix `Get::filter()` issue where keys are URL-decoded
- Fix `Get::query()` issue where nested arrays may lose their structure
- Fix issue where `Process::wait()` fails after process terminates
- Fix `HttpRequest` issue where authority-form request-targets cannot be applied
- In `HttpRequest`, accept arbitrary request methods and preserve their original case in all contexts
- In `HttpHeaders::addLine()`, reject invalid line folding if `$strict` is `true`

### Security

- Always `chmod()` after `mkdir()` in case umask modifies permissions

## [v0.99.13] - 2024-03-19

### Added

- Add `File` methods:
  - `maybeRewind()`
  - `maybeSeek()`
  - `truncate()`
  - `getSeekable()`
- Add `Str::trimNativeEol()`

### Changed

- Add PHPStan return type extensions for `Str::coalesce()` and `Get::coalesce()`, and allow them to be called with no arguments
- Add optional `$offset` parameter to `File::getContents()`
- Optionally truncate target streams in `File::copy()`
- Move methods from `Salient\Contract` to other namespaces (coverable code does not belong in contract namespaces)
- In `Process`:
  - Allow a process to run more than once
  - Allow process output to be passed to a callback as it is received
  - Add `Process::setInput()`, `Process::setCallback()`
  - Split `Process::run()` into `run()`, `start()` and `wait()`
  - Trim trailing native EOL sequences by default

### Removed

- Remove unused `Assert` class and related classes

### Security

- Fix `File::createDir()` and `File::createTempDir()` issues where permissions are not applied on Windows

## [v0.99.12] - 2024-03-13

### Added

- Add `File::chmod()`
- Add `File::existing()`
- Add `File::readLine()`
- Add `File::rewind()`
- Add `File::size()`
- Add `File::type()`
- Add stream-to-stream support to `File::copy()`
- Add `Process::withShellCommand()`

### Changed

- Rename `File::cwd()` to `File::getCwd()`
- Optionally change file modes if necessary for deletion to succeed in `File::deleteDir()` and `File::pruneDir()`
- Improve error reporting in `File`

### Fixed

- Fix issue where `File::createTempDir()` tries to create a temporary directory in `/` if `$directory` is an empty string

### Security

- Fix `File::createDir()` issue where permissions are not applied on Windows and may be affected by process umask on other platforms

## [v0.99.11] - 2024-03-08

### Changed

- Rename `AbstractTypedCollection::clone()` to `copy()` and include it in `CollectionInterface`
- Rename `AccessTokenInterface::getType()` to `getTokenType()`
- Reverse order of first two parameters in `HttpRequest::__construct()` for consistency with PSR-17 `RequestFactoryInterface::createRequest()`

### Fixed

- Fix `Curler` issue where empty responses are incorrectly parsed as JSON

## [v0.99.10] - 2024-03-06

### Changed

- In `SyncEntityProvider`, if an entity has no provider interface, check for a parent that:

  - implements `SyncEntityInterface`
  - resolves to the entity, indicating it has been bound to a "base" entity in the service container
  - has a provider interface

  This allows downstream code to use `$provider->with(MyCustomEntity::class)` instead of `$provider->with(BaseEntity::class)` when `MyCustomEntity` extends `BaseEntity`

- Add explicit `@return` types to methods with native return type `self` to work around bugs in static analysis tools
- Detect associative arrays in "generate sync entity" command

### Fixed

- Fix regression where `AbstractSyncDefinition::getFallbackClosure()` always throws an exception

## [v0.99.9] - 2024-03-06

### Added

- Add `Reflect::isMethodInClass()`
- Add `Sync::hasRunId()`

### Changed

- `SyncContext`: return `null`, not `[]`, when a filter/value is missing
- Add optional `$fromClass` parameter to `getAllMethodDocComments()`

### Fixed

- Fix issue where an unresolved `CliOptionBuilder` instance triggers an unhandled exception if it receives an invalid value from the environment
- Fix issue where `Cache::getInstanceOf()` fails when a non-object is stored under the given key

## [v0.99.8] - 2024-03-05

### Fixed

- Fix (additional) `AbstractSyncDefinition::bindOverride()` issues reported by downstream PHPStan

## [v0.99.7] - 2024-03-05

### Fixed

- Fix `AbstractSyncDefinition::bindOverride()` issues reported by downstream PHPStan

## [v0.99.6] - 2024-03-05

### Added

- Add `Arr::snakeCase()`
- Add `bindOverride()` to sync definition classes

### Changed

- In `AbstractSyncEntity`, do not expand removable prefixes returned by inheritors
- In "generate sync entity" command:
  - Add `--trim` option
  - Always try to detect dates and times in reference entities

### Fixed

- Fix "generate sync entity" issue where relationship class is incorrectly normalised
- Fix `Pcre::split()` return type

## [v0.99.5] - 2024-03-04

### Added

- Add `SyncContextInterface::getFilterInt()`
- Add `SyncContextInterface::getFilterString()`
- Add `SyncContextInterface::getFilterArrayKey()`
- Add `SyncContextInterface::getFilterIntList()`
- Add `SyncContextInterface::getFilterStringList()`
- Add `SyncContextInterface::getFilterArrayKeyList()`
- Add `SyncContextInterface::claimFilterInt()`
- Add `SyncContextInterface::claimFilterString()`
- Add `SyncContextInterface::claimFilterArrayKey()`
- Add `SyncContextInterface::claimFilterIntList()`
- Add `SyncContextInterface::claimFilterStringList()`
- Add `SyncContextInterface::claimFilterArrayKeyList()`

### Changed

- Rename `SyncContextInterface` method `withArgs()` to `withFilter()` and:
  - Always `trim()` keys
  - Convert space-delimited alphanumeric keys to snake_case, not just hyphen- and underscore-delimited keys
  - Don't convert keys to snake_case if non-alphanumeric characters appear at the beginning or end of the key
  - Convert `DateTimeInterface` values to `string`
- Make `SyncContextInterface::getFilter()` parameter `$key` nullable and return all filters when `null`
- Make `<key>` and `<key>_id` interchangeable in `SyncContext` filters with `array-key` values
- Adopt same types for `ProviderContextInterface` values as for `SyncContextInterface` filters
- Rename `SyncInvalidFilterException` to `SyncInvalidFilterSignatureException` before adding `SyncInvalidFilterException` for more general filter-related exceptions
- Extend `HasContainer` from `SyncSerializeRulesInterface` so detached entities can resolve the possibly non-global `SyncStore` with which they are registered
- In `SyncSerializeRules`, implement `HasContainer` and remove unused `Readable` implementation
- Add template and conditional return type to `Arr::wrap()`
- Simplify `Arr::toIndex()`
- Remove `class-string[]` from `ServiceAwareInterface::getService()` return type, reverting to `class-string` only
- Update "generate builder" command to ignore `ContainerInterface` parameters
- Rename `app()` and `container()` to `getApp()` and `getContainer()`

### Removed

- Remove redundant `SyncContextInterface::getFilters()` method
- Remove sync entity methods `setEntityTypeId()` and `getEntityTypeId()` to decouple entities from the global `SyncStore`

### Fixed

- Fix `Introspector` issue where constructor arguments are not matched correctly after service parameters where `null` is passed to the container
- Fix `Introspector` issue where `null` may be passed to non-nullable constructor parameters that are passed by reference

## [v0.99.4] - 2024-03-01

### Added

- Add `Test::isNumericKey()`

### Changed

- In pipelines, accept closures with narrower parameter types
- In sync contexts, reject numeric keys in filters and specify the structure of a valid filter
- Add more `@template` workarounds for PHPStan and Intelephense

### Fixed

- Fix issue where `HasImmutableProperties` creates unnecessary clones when `null` is assigned

## [v0.99.3] - 2024-02-29

### Added

- Add `Env::getClass()`, `Get::notNull()`
- Add `SyncStoreLoadedEventInterface`

### Changed

- Consolidate `Salient\Catalog` into `Salient\Contract`
- Adopt PHPStan level 9 (albeit with 932 errors in baseline)
- `DbConnector`: improve error handling, add stubs to fix ADOdb type issues
- Work around buggy implementations of `@template` in PHPStan and Intelephense in `throwOnFailure()` methods of `DbConnector`, `File` and `Process`

## [v0.99.2] - 2024-02-28

### Added

- Add `BeforeGlobalContainerSetEventInterface`

### Changed

- Move critical interfaces, enumerations and dictionaries to namespaces in `Salient\Contract` and `Salient\Catalog` to decouple them from their respective components

## [v0.99.1] - 2024-02-28

### Changed

- Add `lkrms/util` to `conflict` in `composer.json`
- Decouple `RequiresContainer` trait from `Container` by falling back to `App::getInstance()`, which can be bound to any implementation
- Similarly, use `RequiresContainer` to decouple `ConstructibleTrait` from `Container`
- `ConstructibleTrait`: always throw an exception if values are not used
- `Constructible`: add `$parent` parameters, method documentation

### Removed

- Remove `maybeGetGlobalContainer()` and `requireGlobalContainer()` methods from `Container`
- Remove `ContainerNotFoundException`

## [v0.99.0] - 2024-02-26

This is the first release of `salient/toolkit`, the PHP toolkit formerly known as `lkrms/util`.

## [v0.21.49] - 2024-02-26

This is the final release of `lkrms/util`. It is moving to [Salient](https://github.com/salient-labs/) as `salient/toolkit`.

### Changed

- Sync: move abstract and high-priority classes to `Salient\Sync`
- Rename:
  - `ISyncClassResolver` -> `SyncClassResolverInterface`
  - `ISyncContext` -> `SyncContextInterface`
  - `ISyncDefinition` -> `SyncDefinitionInterface`
  - `ISyncEntity` -> `SyncEntityInterface`
  - `ISyncEntityProvider` -> `SyncEntityProviderInterface`
  - `ISyncEntityResolver` -> `SyncEntityResolverInterface`
  - `ISyncProvider` -> `SyncProviderInterface`
  - `ISyncSerializeRules` -> `SyncSerializeRulesInterface`
  - `SyncDefinition` -> `AbstractSyncDefinition`
  - `SyncEntity` -> `AbstractSyncEntity`
  - `SyncOperations` -> `SyncOperationGroup`
  - `SyncProvider` -> `AbstractSyncProvider`
  - `SyncException` -> `AbstractSyncException`
  - `SyncEvent` -> `AbstractSyncEvent`
  - `SyncStoreEvent` -> `AbstractSyncStoreEvent`

## [v0.21.48] - 2024-02-26

### Added

- Add `BadMethodCallException`

### Changed

- Return `null` from `Cache::getInstanceOf()`, `Cache::getArray()`, `Cache::getInt()`, `Cache::getString()` instead of throwing an exception when the cached item is of the wrong type
- In `SqliteStore`, implement `Unloadable`, throw an exception if `getFilename()` is called when the database is not open, improve documentation
- In `ImmutableArrayAccess`, throw `BadMethodCallException` instead of `LogicException`
- Use `ImmutableArrayAccess` in `ConfigurationManager`
- Make `HasBuilder::getBuilder()` protected
- Improve `ContainerInterface::getAs()` generics
- Ignore `@throws` tags in `PhpDoc::hasDetail()`
- Move remaining classes, interfaces and traits to `Salient`
- Rename:
  - `ArrayKeyConformity` -> `ListConformity`
  - `Entity` -> `AbstractEntity`
  - `HasDateProperties` -> `Temporal`
  - `HasDescription` -> `Describable`
  - `HasIdentifier` -> `Identifiable`
  - `HasJsonSchema` -> `JsonSchemaInterface`
  - `HasName` -> `Nameable`
  - `HasParentProperty` -> `Treeable`
  - `HasProviderContext` -> `ProviderContextAwareInterface`
  - `IConstructible` -> `Constructible`
  - `IEntity` -> `EntityInterface`
  - `IExtensible` -> `Extensible`
  - `IProvidable` -> `Providable`
  - `IProvider` -> `ProviderInterface`
  - `IProviderContext` -> `ProviderContextInterface`
  - `IProviderEntity` -> `ProvidableEntityInterface`
  - `IRelatable` -> `Relatable`
  - `IResolvable` -> `Normalisable`
  - `ISerializeRules` -> `SerializeRulesInterface`
  - `ITreeable` -> `HierarchyInterface`
  - `Provider` -> `AbstractProvider`
  - `ReceivesProvider` -> `ProviderAwareInterface`
  - `RelationshipType` -> `Cardinality`
  - `ReturnsNormaliser` -> `NormaliserFactory`
  - `SqliteStore` -> `AbstractStore`
  - `TtyControlSequence` -> `EscapeSequence`
  - `ImmutableArrayAccess` -> `ImmutableArrayAccessTrait`
  - `ICollection` -> `CollectionInterface`
  - `TCollection` -> `CollectionTrait`
  - `TImmutableCollection` -> `ImmutableCollectionTrait`
  - `TypedCollection` -> `AbstractTypedCollection`
  - `IList` -> `ListInterface`
  - `TList` -> `ListTrait`
  - `TImmutableList` -> `ImmutableListTrait`
  - `TypedList` -> `AbstractTypedList`
  - `TReadableCollection` -> `ReadableCollectionTrait`
  - `HasParent` -> `TreeableTrait`
  - `IComparable` -> `Comparable`
  - `IStoppableEvent` -> `StoppableEventInterface`
  - `TConstructible` -> `ConstructibleTrait`
  - `TExtensible` -> `ExtensibleTrait`
  - `TProvidable` -> `ProvidableTrait`
  - `TStoppableEvent` -> `StoppableEventTrait`
- Do not extend `Normalisable` from `NormaliserFactory`
- Extend `HasProvider` from `ProviderAwareInterface`
- Merge `HasChildrenProperty` into `Treeable`
- Merge `HasProviderContext` into `ProviderContextAwareInterface`
- Merge `HasService` into `ServiceAwareInterface`
- Rename `HasProvider::provider()` to `getProvider()`
- Rename `HierarchyInterface::countDescendants()` to `getDescendantCount()`
- Rename `NormaliserFactory::normaliser()` to `getNormaliser()`
- Rename `ProviderContextAwareInterface::context()` to `getContext()`
- Rename `ServiceAwareInterface::service()` to `getService()`
- Move `IEntity::plural()` to `ISyncEntity`
- Move `SerializeRulesInterface::getFlags()` to `ISyncSerializeRules`

### Removed

- Remove `Sys::sqliteHasUpsert()`
- Remove `Buildable::getBuilder()`
- Remove `TokenExtractor` (moved to `Lkrms\LkUtil` namespace)

### Fixed

- `Cache`: fix issue where an exception is thrown when an instance of a class that no longer exists is returned from the cache

## [v0.21.47] - 2024-02-23

### Changed

- Move classes into the `Salient` namespace:
  - `Lkrms\Container\*` -> `Salient\Container\*`
  - `Lkrms\Cli\*` -> `Salient\Cli\*`
  - `Lkrms\Console\*` -> `Salient\Console\*`
  - `Lkrms\Concept\Builder` -> `Salient\Core\AbstractBuilder`
  - `Lkrms\Concern\HasBuilder` -> `Salient\Core\Concern\HasBuilder`
  - `Lkrms\Contract\Buildable` -> `Salient\Core\Contract\Buildable`
  - `Lkrms\Contract\HasContainer` -> `Salient\Container\Contract\HasContainer`
  - `Lkrms\Contract\HasService` -> `Salient\Container\Contract\HasService`
  - `Lkrms\Facade\App` -> `Salient\Core\Facade\App`
  - `Lkrms\Facade\Console` -> `Salient\Core\Facade\Console`
  - `Lkrms\Facade\Err` -> `Salient\Core\Facade\Err`
  - `Lkrms\Facade\Profile` -> `Salient\Core\Facade\Profile`
  - `Lkrms\Support\Catalog\TtyControlSequence` -> `Salient\Core\Catalog\TtyControlSequence`
  - `Lkrms\Support\ErrorHandler` -> `Salient\Core\ErrorHandler`
  - `Lkrms\Support\MetricCollector` -> `Salient\Core\MetricCollector`

## [v0.21.46] - 2024-02-23

### Added

- **Add `Process` and supporting classes**
- Add `Env::flag()`
- Add `Sys::isWindows()`
- Add `Pcre::delimit()`

### Changed

- **Replace `Get::apparent()` with `Get::arrayKey()`**
- **In `Env::apply()`, don't throw an exception when `setlocale()` fails**
- Allow `Env::get*()` default values to be closures

### Removed

- Remove `Regex::delimit()` and `Regex::anchorAndDelimit()`

### Fixed

- **Fix inconsistent handling of non-empty whitespace-only strings by preserving them without collapsing to `null` in `Env::getNullable()`**

## [v0.21.45] - 2024-02-21

### Added

- Add `SyncEntityRecursionException`
- Add `ISyncContext::pushWithRecursionCheck()`
- Add `ISyncContext::maybeThrowRecursionException()`

### Changed

- Sync: prevent infinite recursion during entity hydration
  - Call `ISyncContext::pushWithRecursionCheck()` instead of `ISyncContext::push()` where necessary
  - Call `ISyncContext::maybeThrowRecursionException()` before requesting entities from providers
- In `GetSyncEntities`, apply `DeferralPolicy::DO_NOT_RESOLVE` in addition to `HydrationPolicy::SUPPRESS` when `--shallow` is given

### Fixed

- Fix issue where deferred relationships are created to hydrate entities with no identifier

## [v0.21.44] - 2024-02-20

### Added

- Add `ITreeable::countDescendants()` and implement in `HasParent`

## [v0.21.43] - 2024-02-20

### Added

- Add `HasFacade::updateFacade()` to improve support for facades with immutable underlying instances

### Changed

- Make `Get::binaryHash()` and `Get::hash()` parameter `$value` non-variadic
- Move `Test::firstExistingDirectoryIsWritable()` to `File::creatable()` and accept file names, not just directories
- Allow filesystem-related assertions to throw exceptions other than `FilesystemErrorException`
- Improve `Pcre` error reporting
- Continue migration to `Salient` namespace
  - Move utility classes to `Salient\Core\Utility`
  - Move `EventHandler` and related classes to `Salient\Core`
  - Move enumeration- and dictionary-related interfaces and classes to `Salient\Core` and rename:
    - `IEnumeration` -> `EnumerationInterface`
    - `IConvertibleEnumeration` -> `ConvertibleEnumerationInterface`
    - `IDictionary` -> `DictionaryInterface`
    - `Catalog` -> `AbstractCatalog`
    - `Enumeration` -> `AbstractEnumeration`
    - `ConvertibleEnumeration` -> `AbstractConvertibleEnumeration`
    - `ReflectiveEnumeration` -> `AbstractReflectiveEnumeration`
    - `Dictionary` -> `AbstractDictionary`
  - Move `RegularExpression` and `CharacterSequence` to `Salient\Core\Catalog` and rename:
    - `RegularExpression` -> `Regex`
    - `CharacterSequence` -> `Char`
  - Move exceptions to `Salient\Core` and rename:
    - `Exception` -> `AbstractException`
    - `MultipleErrorException` -> `AbstractMultipleErrorException`
    - `IncompatibleRuntimeEnvironmentException` -> `InvalidRuntimeConfigurationException`
    - `PipelineResultRejectedException` -> `PipelineFilterException`
    - `InvalidDotenvSyntaxException` -> `InvalidDotEnvSyntaxException`
  - Move pipeline- and immutable-related interfaces, classes and traits to `Salient\Core` and rename:
    - `IFluentInterface` -> `Chainable`
    - `FluentInterface` -> `HasChainableMethods` (class -> trait)
    - `Immutable` -> `HasImmutableProperties`
    - `IImmutable` -> `Immutable`
    - `IPipeline` -> `PipelineInterface`
    - `IPipe` -> `PipeInterface`
- Refactor pipelines
  - Require closures where callables were previously sufficient
  - Rename `throughCallback()` to `throughClosure()`
  - Split `PipelineInterface` into multiple interfaces to surface different methods after `send()` or `stream()` are called, and to prevent useless early calls to `withConformity()`
- Add `fromNames()` and `toNames()` to `ConvertibleEnumerationInterface` and implement in `AbstractConvertibleEnumeration` and `AbstractReflectiveEnumeration`
- Move `Convert::toNormal()` to `Str::normalise()`
- Move `Convert::queryToData()` to `Get::filter()` and refactor to take advantage of `parse_str()`
- Move `Convert::dataToQuery()` to `Get::query()` and add `QueryFlag` for more precise control of serialization
- Move `Convert::uuidToHex()` to `Get::uuid()` and allow the given UUID to be in hexadecimal form already
- Add `Compute::binaryUuid()` and remove `$binary` parameter from `Compute::uuid()`
- Move `Convert::unwrap()` to `Str::unwrap()`
- Move `Convert::toShellArg()` and `Convert::toCmdArg()` to `Sys` as private methods `escapeShellArg()` and `escapeCmdArg()`
- Move `Compute` methods `binaryUuid()`, `uuid()`, `randomText()`, `binaryHash()`, `hash()` to `Get`
- Move `Compute` methods `textDistance()` and `textSimilarity()` to `Str::distance()` and `Str::similarity()` respectively
- Move `ngramSimilarity()`, `ngramIntersection()`, `ngrams()` from `Compute` to `Str`
- Rename `ResolvesServiceLists` trait to `HasUnderlyingService`
- Rename `SortTypeFlag` to `SortFlag`

### Removed

- Remove `PipelineFilterException` (previously `PipelineResultRejectedException`) because `unless()` and `send()` cannot be used together
- Remove empty `Compute` class
- Remove `Convert` class, including unused methods `toStrings()` and `objectToArray()`
- Remove unused `IsConvertibleEnumeration` trait

### Fixed

- Fix `HasFacade` issue where instances are not always reused
- Fix issue where `AbstractFacade::unload()` does not unload service lists

## [v0.21.42] - 2024-02-15

### Added

- Add service container methods `hasSingleton()` and `unbindInstance()`

### Changed

- Leave `unload()`ed service containers in a usable state

### Fixed

- Fix issue where `HasFacade` doesn't remove references to cloned instances when unloaded
- Fix issue where service container bindings are forgotten when a facade's underlying instance is removed from a container

## [v0.21.41] - 2024-02-15

### Added

- Add `$configDir` parameter to `CliApplication::__construct()`

### Changed

- Sync: add deferred entity and relationship types to generated entities

## [v0.21.40] - 2024-02-14

### Added

- Add `ConfigurationManager` and the `Config` facade for simple configuration file handling
- Add `Arr::get()` and `Arr::has()`

### Changed

- **Load application configuration files from `<basePath>/config` by default**
- Rename `Facade` to `AbstractFacade`

## [v0.21.39] - 2024-02-12

### Added

- Add collection methods `only()`, `onlyIn()`, `except()`, `exceptIn()`
- Add `Curler::getPublicHeaders()`
- Add `Get::count()`

### Changed

- **Move `$count` / `$from` / `$to` to the start of `Inflect::format*()` method signatures**
- In `Inflect::format()` and `Inflect::formatWithSingularZero()`, allow `$count` to be an array, `Countable`, `Arrayable` or `Traversable`, not just an `int`

## [v0.21.38] - 2024-02-12

### Changed

- Move `Facade` and related interfaces and traits to `Salient\Core`
- Rename `IReadable` and `IWritable` to `Readable` and `Writable` and move to `Salient\Core\Contract`
- Rename `Readable::getReadable()` to `getReadableProperties()`
- Rename `Writable::getWritable()` to `getWritableProperties()`
- Rename `TReadable` and `TWritable` to `HasReadableProperties` and `HasWritableProperties` and move to `Salient\Core\Concern`
- Rename `TFullyReadable` and `TFullyWritable` to `ReadsProtectedProperties` and `WritesProtectedProperties` and move to `Salient\Core\Concern`

## [v0.21.37] - 2024-02-07

### Added

- Add `ApplicationInterface::getWorkingDirectory()`

### Fixed

- Fix `ConsoleFormatter` bug where trailing code spans do not unwrap
- Fix inconsistent `float` detection in `Test::isFloatValue()` on PHP 7.4

## [v0.21.36] - 2024-02-07

### Added

- Add `Str::matchCase()`
- Add `Inflect::formatRange()`

### Changed

- Move `Convert::toValue()` to `Get::apparent()`
- Move `Convert::toInt()` to `Get::integer()`
- Move `Convert::linesToLists()` to `Str::mergeLists()`
- Move `ellipsize()`, `expandTabs()` and `expandLeadingTabs()` from `Convert` to `Str`
- In `Inflect::format()`:
  - Allow arbitrary placement of whitespace and hyphens in words
  - Allow words to be empty
  - Add `#` as a recognised word
  - Match the case of the placeholder

### Removed

- Remove `Convert::pluralRange()` in favour of `Inflect::formatRange()`
  - To replace a `Convert::pluralRange()` call with the equivalent `Inflect::formatRange()` call:

    ```php
    <?php
    // Before:
    Convert::pluralRange($from, $to, '<noun>');

    // After:
    Inflect::formatRange('{{#:on:between}} {{#:<noun>}} {{#:#:and}}', $from, $to);
    ```

### Security

- Fix issue where `Get::value()` may inadvertently call an arbitrary function by limiting callables to `Closure` instances

## [v0.21.35] - 2024-02-06

### Added

- Add `Inflect::format()`
- Add `File::readCsv()`

### Changed

- Move `Convert::intervalToSeconds()` to `Date::duration()`
- Move `Convert::toBool()` to `Get::boolean()`
- Move `Convert::nounToPlural()` to `Inflect::plural()`
- Remove `Convert::plural()` in favour of `Inflect::format()`

### Fixed

- Fix issue where `Date::duration()` silently discards weeks in durations like `'P1W2D'` on PHP 7.4

## [v0.21.34] - 2024-02-05

### Added

- Add `ContainerInterface::addContextualBinding()`
- Add `ContainerInterface::hasProvider()`
- Add `HasBindings` interface
- Add `Get::value()`

### Changed

- Rename `IServiceSingleton` to `SingletonInterface` and do not extend `IService`
- Split `IService` into `HasServices` and `HasContextualBindings`
- Rename `ReceivesContainer` to `ContainerAwareInterface` and change return type of `setContainer()` to `void`
- Rename `ReceivesService` to `ServiceAwareInterface` and change return type of `setService()` to `void`
- Rename container `service` methods to `provider` equivalents
- In `ContainerInterface` / `Container`:
  - Return `void` from `setGlobalContainer()`
  - Throw `ContainerUnusableArgumentsException` when arguments are given but cannot be passed to a constructor
  - Remove `$shared` parameter from `bind()`, `singleton()`, etc., and make `$args` parameter non-nullable
  - Make `provider()` parameter `$exceptServices` non-nullable
  - In `providers()`, require service providers to be mapped (they can be mapped to themselves if otherwise unbound), and allow any class to be a service provider, not just `HasServices` (formerly `IService`) implementors
- Use `ServiceLifetime` as a standard enumeration, not a bitmask
- Rename `ContainerNotLocatedException` to `ContainerNotFoundException`
- Rename `GlobalContainerSetEvent` to `BeforeGlobalContainerSetEvent`
- Move `ApplicationInterface::getProgramName()` to `CliApplicationInterface`
- Remove `App` facade
- Rename `DI` facade to `App`
- Replace underlying instance of `App` with global container on `BeforeGlobalContainerSetEvent`
- Always unload facades in `ErrorHandler` and `SqliteStore`
- Move `ContainerInterface` and `ApplicationInterface` to `Lkrms\Container`
- Finalise Container API for v1.0

### Removed

- Remove unused `ContainerInterface::getContextStack()`
- Remove unused `ServiceSingletonInterface` and `ServiceLifetime::SERVICE_SINGLETON` and their respective implementations
- Remove unused `TService` trait

### Fixed

- Fix issue where `Container` binds itself to `FluentInterface`
- Fix issue where `Container::has()` returns `false` when it has a shared instance with the given identifier
- Fix issue where conditional bindings (e.g. `bindIf()`) are registered with containers that already have a shared instance with the given identifier
- Fix issue where `BeforeGlobalContainerSetEvent` is not dispatched when a container is created via `Container::getGlobalContainer()`
- Fix issue where the global container is unbound from itself after it is unloaded from a facade

## [v0.21.33] - 2024-01-30

### Changed

- Rename `IContainer` to `ContainerInterface` and move to `Lkrms\Container\Contract`
- Rename `IApplication` to `ApplicationInterface` and move to `Lkrms\Container\Contract`
- Rename Cli interfaces:
  - `ICliApplication` -> `CliApplicationInterface`
  - `ICliCommand` -> `CliCommandInterface`
  - `ICliCommandNode` -> `CliCommandNodeInterface`
- Move `CliHelpStyle` from `Lkrms\Cli\Support` to `Lkrms\Cli`, removing unnecessary `Lkrms\Cli\Support` namespace
- Finalise v1.0 Cli API

### Fixed

- Cli: fix issue where `IsBound` may be true for unbound options

## [v0.21.32] - 2024-01-29

### Added

- Add `Application::setWorkingDirectory()`
- Add `File::chdir()`

### Fixed

- Throw an exception when `Application::restoreWorkingDirectory()` fails

## [v0.21.31] - 2024-01-29

### Added

- Add `Application::restoreWorkingDirectory()`
- Add `$inSchema` parameter to `CliOption::__construct()`

### Changed

- **In `CliCommand::getOptionValues()`, add optional `$unexpand` parameter, and suppress value-optional options not given on the command line**

### Fixed

- Fix `CliCommand::applyOptionValues()` issue where value-optional options are expanded on export after they are applied

## [v0.21.30] - 2024-01-28

### Added

- Add `IContainer::hasInstance()`
- Add `FacadeInterface::swap()`
- Add `HasFacade` and `UnloadsFacades` traits and adopt where possible
- Allow `Facade::getService()` to return a service/alias list
- Add optional `alias` option to `lk-util generate facade` command
- Add `ResolvesServiceLists` trait and use in `Facade` to normalise service lists
- Add `Get::fqcn()`

### Changed

- Rename `IFacade` to `FacadeInterface`
- Replace forwarded constructor parameters in `FacadeInterface::load()` with an optional `$instance` parameter, and change return type to `void`
- Rename `ReceivesFacade` to `FacadeAwareInterface`
- Remove `FacadeAwareInterface` method `setFacade()` in favour of immutable-friendly `withFacade()` and `withoutFacade()` methods
- Rename `Facade` method `getServiceName()` to `getService()` for consistency with other classes
- In `Facade::unload()`, check the global container resolves the facade's service name to the underlying instance before unbinding it
- **In `Container::has()`, only return `true` if the container has an explicit binding for `$id`**
- Add optional `$classes` parameter to `Get::code()` for output like `<class>::class` instead of `"<class>"`
- In `Get::code()`, convert `float` values with an exponent to lowercase
- Decouple `ConsoleWriter` state from the `ConsoleWriter` class to take advantage of the new facade API

### Fixed

- Work around "Private methods cannot be final as they are never overridden by other classes" bug in PHP 8.3.2 by replacing `IsCatalog` trait with equivalent class `Catalog`
- Fix `Container` issue where shared instances are discarded when created via `getAs()`
- Fix `Introspector` issue where an exception is thrown if a constructor has a variadic parameter
- Fix issue where `Facade::unloadAll()` fails with "No listener with that ID"

## [v0.21.29] - 2024-01-24

### Added

- Add counters to metrics collected by `Timekeeper` and its `Profile` facade
- Add `Arr::keyOf()`

### Changed

- Rename `Timekeeper` to more accurate `MetricCollector`
- Rename "timer types" to "metric groups"
- Rename `MetricCollector`/`Profile` methods:
  - `pushTimers()` => `push()`
  - `popTimers()` => `pop()`
- Rename `IApplication::reportTimers()` to `reportMetrics()`
- Report counters and timers subsequently in `Application` output
- Improve `Pcre::replaceCallback()` and `Pcre::replaceCallbackArray()` annotations for PHPStan users
- Move utility methods:
  - `Convert::coalesce()` => `Get::coalesce()`
  - `Convert::sizeToBytes()` => `Get::bytes()`
  - `Convert::valuesToCode()` => `Get::code()`
  - `Sys::getCwd()` => `File::cwd()`

### Fixed

- Fix `Application` issues where:
  - All metrics output is bold
  - Numeric timer names are lost if the number of timers/counters reported is limited
- Fix issue where `Get::bytes()` is less forgiving than PHP's ini parser
- Fix issue where `min` and `max` in integer ranges (e.g. `int<1,max>`) are treated as class names

## [v0.21.28] - 2024-01-20

### Added

- Add `Unloadable` interface
- Add `ConsoleTargetInterface::close()`
- Add `ConsoleTargetStreamInterface::reopen()`
- Add `ConsoleInvalidTargetException`
- Add `Console::deregisterAllTargets()` and call it when the `Console` facade's underlying `ConsoleWriter` is unloaded

### Changed

- If a facade's underlying instance implements `Unloadable`, call its `unload()` method before removing it from the facade
- In `Console::deregisterTarget()`, call `close()` on deregistered targets
- In `File::same()`, return earlier if filenames are identical

### Fixed

- Fix issue where the first underlying instance loaded by `Facade` is never completely unloaded because its container event listener is never removed
- Fix issue where `Facade::unloadAll()` bypasses deregistrations applied in `Facade::unload()`

## [v0.21.27] - 2024-01-20

### Changed

- Add optional `$directory` and `$prefix` arguments to `File::createTempDir()`
- Add optional `$forgetArguments` parameter to `CliCommand::applyOptionValues()`

## [v0.21.26] - 2024-01-20

### Added

- Add `CliCommand::optionHasArgument()`
- Add `ICliApplication::getLastCommand()` and implement in `CliApplication`

### Changed

- Make `CliCommand::getRuns()` public

### Removed

- Remove unused `Assert::isMatch()`

## [v0.21.25] - 2024-01-19

### Added

- Add `Str::eolToNative()` and `Str::eolFromNative()`

### Changed

- In `Str::setEol()`, normalise strings with multiple end-of-line sequences, reverting change made in v0.21.19
- Replace multi-line strings in `Console` and `Cli` to ensure the only end-of-line sequence used internally is "\n"
- Don't enable PCRE's Unicode flag unnecessarily in `ConsoleFormatter`

## [v0.21.24] - 2024-01-19

### Fixed

- Fix issue where Cli options cannot be applied to cloned commands

## [v0.21.23] - 2024-01-19

### Added

- Add `Get::copy()`, a deep copy implementation similar to `myclabs/deep-copy`
- Add `Reflect::getAllProperties()`
- Add `UncloneableObjectException`
- Add a `UnitEnum` stub for PHP <8.1

### Changed

- Return without declaring stubs that have already been declared

### Fixed

- Fix issue where Cli options are still bound to their original variables / properties after a command is cloned (caveat: commands must be cloned with `Get::copy()`)

## [v0.21.22] - 2024-01-18

### Changed

- Use native line endings in `Json::prettyPrint()` and elsewhere previously overlooked

### Fixed

- Cli: fix regression where terse synopses are double-escaped

## [v0.21.21] - 2024-01-17

### Added

- Add `CliCommand` methods:
  - `filterGetSchemaValues()`
  - `filterNormaliseSchemaValues()`
  - `filterApplySchemaValues()`
- Add unified diff formatting via `ConsoleFormatter::formatDiff()`
- Add `Console::getFormatter()`
- Add `ConsoleFormatterFactory` and implement in:
  - `ConsoleLoopbackFormat`
  - `ConsoleMarkdownFormat`
  - `ConsoleManPageFormat`
- Add `File::dir()`

### Changed

- Cli: refactor `CliCommand`
  - Provide default implementations of `getOptionList()`, `getLongDescription()`, `getHelpSections()`
  - Add explicit schema value handling to `applyOptionValues()`, `normaliseOptionValues()`, `getOptionValues()`, `getDefaultOptionValues()`
  - Allow schema names to be used with `getOptionValue()`, `getOption()`, `hasOption()`

- Cli: improve help messages, incl. Markdown and man page output
  - Consolidate formatting code into `CliHelpStyle` where possible
  - Replace value name with `yes|no` for options with value type `BOOLEAN`
  - Include syntax when wrapping Markdown and man page output
  - Always wrap synopses

- Allow `HasDescription` to be implemented without `HasName`
- Adopt PHIVE for tools

### Removed

- Remove clumsy/redundant `ICliApplication` methods `getHelpType()`, `getHelpStyle()`, `getHelpWidth()`, `buildHelp()` and their implementations

## [v0.21.20] - 2024-01-10

### Added

- Add `DotNetDateParser`

### Changed

- Move `DateFormatter` and `*DateParser` classes and interfaces to `Lkrms\Support\Date`
- Rename:
  - `IDateFormatter` -> `DateFormatterInterface`
  - `IDateParser` -> `DateParserInterface`
  - `TextualDateParser` -> `DateParser`
  - `CreateFromFormatDateParser` -> `DateFormatParser`

### Removed

- Remove `RegexDateParser`

## [v0.21.19] - 2024-01-08

### Added

- Add `Str::upperFirst()` and use it in favour of `ucfirst()`
- Add `Test::isDateString()`
- Add `File::copy()`
- Add `Console::getTargets()`
- Add `lk-util` command `sync entity get`

### Changed

- Allow filenames to be passed to `File::stat()`, not just streams
- Rename `File::is()` to `File::same()`
- Clean up `Console` classes
  - Make `Console::getStdoutTarget()` and `Console::getStderrTarget()` public to surface mockable output streams
  - **Use new `ConsoleTargetTypeFlag` enumeration to simplify `Console::setTargetPrefix()` parameters**
  - Rename `IConsoleTarget` to `ConsoleTargetInterface` and split into additional `ConsoleTargetStreamInterface`
  - Rename `IConsoleTargetWithPrefix` to `ConsoleTargetPrefixInterface` and add `getPrefix()` method
  - Split abstract `ConsoleTarget` class into additional `ConsolePrefixTarget` and `ConsoleStreamTarget` classes
  - Rename `ConsoleLevels` to `ConsoleLevelGroup`
  - Rename `ConsoleMessageTypes` to `ConsoleMessageTypeGroup`
  - Rename `IConsoleFormat` to `ConsoleFormatInterface`
  - Rename `Console::registerStableOutputLog()` to `registerLogTarget()`
  - Rename `Console::registerDefaultStdioTargets()` to `maybeRegisterStdioTargets()`
  - Rename `ConsoleMessageType::DEFAULT` to `ConsoleMessageType::STANDARD`
- Improve handling of `null`, empty and default `CliOption` values
  - Add `CliOption::$Nullable` and resolve the value of `nullable` options not given on the command line to `null`
  - **Allow `required` options to have an optional value**
  - **Require options with optional values to have a non-empty `defaultValue`**
  - **Normalise the value of `multipleAllowed` options not given on the command line to an empty array if they are not `nullable`**
- In `lk-util generate` commands:
  - Remove custom metadata tags and `@uses` from output
  - **Remove `--no-meta` option**
  - Add `@api` to output if `--api` is given
  - Add `@generated` to output
- Sync: rename `lk-util` command `heartbeat` to `sync provider check-heartbeat`
- Sync: add `--shallow` option to generic `GetSyncEntities` command

### Removed

- Remove `HasEnvironment` interface and its implementations
- Remove unused `Convert::pathToBasename()` method
- Remove deprecated methods

### Fixed

- Fix an issue where `File::same()` returns `true` for files with the same inode number on different devices
- Fix an issue where `Str::setEol()` replaces every combination of `CR` and `LF` in a string instead of the string's current EOL sequence

## [v0.21.18] - 2023-12-29

### Added

- Add `File::guessIndentation()`
- Add `File::isSeekable()`
- Add `Str::toStream()`
- Add `Sys::getUserId()`

### Changed

- Refactor `File::realpath()`
  - Remove file descriptor handling
  - **Throw an exception if the file doesn't exist**
  - Resolve Phar URIs when a Phar is not running
- Refactor `File::relativeToParent()`
  - Require `$parentDir`
  - Add `$fallback` (`null` by default) and return it if `$filename` does not belong to `$parentDir`
- Refactor `File::writeCsv()`
  - **Make `$resource` a required parameter**
  - **Swap `$data` and `$resource` parameters**
  - **Change return type to `void`**
  - Remove UTF-16LE filters applied to streams provided by the caller
  - Apply `Arr::toScalars()` to each row of data
- Refactor `Sys::getProgramBasename()`
  - Only remove the first matched `$suffix`
- Rename `Stream::fromContents()` to `Stream::fromString()`
- Rename environment variable `CONSOLE_OUTPUT` to `CONSOLE_TARGET`
- Add optional `$null` parameter to `Arr::toScalars()`
- Adopt `Str::lower()` and `Str::upper()` for case comparison
- Make `File::fputcsv()` public

### Fixed

- Fix `File::realpath()` issue where `//../` segments in Phar URIs are not resolved correctly
- Fix `ErrorHandler::silencePath()` issue where files and directories that start with the same name as a silenced file are inadvertently silenced

## [v0.21.17] - 2023-12-25

### Added

- Add `Arr::flatten()`
- Add `HttpFactory` (implements PSR-17 factory interfaces)

### Changed

- **Return `null` instead of `false` when `FluentIteratorInterface::nextWithValue()` finds no matching value**
- Pass value AND key to `FluentIteratorInterface::forEach()` callback
- In `HttpRequest`, preserve the original case of the HTTP method
- In `HttpHeaders`, throw an exception when a header with no values is given
- In `Uri`, do not resolve dot segments if the URI is a relative reference
- In `Arr::toScalars()`, preserve `null` values
- In `Arr::trim()`, remove keys from the array if removing empty values
- Optionally preserve keys in `Arr::unique()`
- Rename `Arr::sameValues()` to `same()`
- Accept `iterable` where possible in `Arr` methods
- Add `Arr::keyOffset()`, deprecating `Convert::arrayKeyToOffset()`
- Add `Arr::toMap()`, deprecating `Convert::listToMap()`
- Add `Arr::toScalars()`, deprecating `Convert::toScalarArray()`
- Add `Get::array()`, deprecating `Convert::iterableToArray()`

### Deprecated

- Deprecate (see above):
  - `Convert::arrayKeyToOffset()`
  - `Convert::listToMap()`
  - `Convert::toScalarArray()`
  - `Convert::iterableToArray()`

### Removed

- Remove unused/redundant methods:
  - `Arr::forEach()`
  - `Convert::columnsToUnique()`
  - `Convert::iterableToItem()`
  - `Convert::iterableToIterator()`
  - `Convert::scalarToString()`
  - `Convert::stringsToUnique()`
  - `Convert::stringsToUniqueList()`
  - `Convert::valueAtKey()`
  - `Convert::walkRecursive()`
  - `FluentIteratorInterface::forEachWhile()`
  - `FluentIteratorTrait::forEachWhile()`

### Fixed

- Fix bug in `Arr::sortDesc()` where keys are not preserved correctly

## [v0.21.16] - 2023-12-21

### Fixed

- Fix regression in 2a19c2d1 where builders cannot create objects from arrays that resolve to exactly 1 constructor parameter

## [v0.21.15] - 2023-12-21

### Added

- Add static `Uri::from()` method
- Add `HttpRequest`
- Add `TReadableCollection::filterItems()`

### Changed

- Build out `CliCommand::getJsonSchema()` and `CliCommand::filterJsonSchema()`
- Rename `Json::toArrays()` to `Json::parseObjectAsArray()`
- Standardise types that can be converted to a `Uri`
- Rename `HttpRequest` to `HttpServerRequest` and replace with PSR-7 `HttpRequest` class (for outgoing requests)
- In `HttpHeaders`, move `Host` headers to the top of the index for compliance with \[RFC7230]

### Fixed

- Fix `HttpHeaders` issue where header names and values with trailing newlines are not rejected as invalid

## [v0.21.14] - 2023-12-20

### Added

- Add `File::openPipe()`, `File::closePipe()`, `File::putContents()`
- Add PHPStan-friendly `@param-out` types to `Pcre::match()` and `Pcre::matchAll()`

### Fixed

- Fix issue where generated facades may import unused classes

## [v0.21.13] - 2023-12-15

### Added

- Add `Stream` class, related exceptions and tests
- Add `HttpMessage` class
- Add `HttpProtocolVersion` enumeration
- Add `InvalidArgumentTypeException`
- Add `Arr::splice()`
- Add `File::read()`, `File::stat()`, `File::getContents()`
- Add `IEnumeration::hasValue()` and implement in `ConvertibleEnumeration`, `ReflectiveEnumeration`, `Enumeration`

### Changed

- Refactor, move and deprecate previous:
  - `Convert::arraySpliceAtKey()` -> `Arr::spliceByKey()`
  - `Convert::renameArrayKey()` -> `Arr::rename()`
  - `Test::isPharUrl()` -> `File::isPharUri()`
  - `Test::isAbsolutePath()` -> `File::isAbsolute()`
- Make `Uri::removeDotSegments()` public, improve documentation
- Change `HttpRequestMethod` from dictionary to enumeration
- Rename for PSR-7 consistency:
  - `IHttpHeaders` -> `HttpHeadersInterface`
  - `IAccessToken` -> `AccessTokenInterface`
- Move classes in `Http\Auth` to `Http\OAuth2`

### Deprecated

- Deprecate (see above):
  - `Convert::arraySpliceAtKey()`
  - `Convert::renameArrayKey()`
  - `Test::isPharUrl()`
  - `Test::isAbsolutePath()`

### Fixed

- Fix issue where `File` methods do not throw `FilesystemErrorException` on failure
- Fix issue where `Test::isAbsolutePath()` does not match URIs or Windows paths with forward slashes, e.g. `C:/path/to/file`
- Fix issue where `Test::isPharUrl()` only matches when scheme name is lowercase

## [v0.21.12] - 2023-12-12

### Added

- In `CliApplication`, generate a JSON Schema when `_json_schema` is the first argument after a command
- Add `Uri::follow()` and `Uri::resolveReference()`, deprecating `Convert::resolveRelativeUrl()` and removing support for obsolete \[RFC1808]
- Add `Uri::parse()`, deprecating `Convert::parseUrl()`
- Add `Uri::unparse()`, deprecating `Convert::unparseUrl()`
- Add `Uri::toParts()`, `Uri::fromParts()`, `Uri::normalise()`, `Uri::isReference()`
- Add `CliCommand::filterJsonSchema()`
- Add `CliOption::getSummary()`

### Changed

- Replace `Sys` facade with renamed `System` class
- Replace `Debug` facade with renamed `Debugging` class after making its methods static
- Refactor `Debug::getCaller()`
- Rename `Inspect` to `Get` and shorten method names, i.e.
  - `Inspect::getType()` -> `Get::type()`
  - `Inspect::getEol()` -> `Get::eol()`
- Move `Convert::classToBasename()` and `Convert::classToNamespace()` to `Get::basename()` and `Get::namespace()`, deprecating the former
- Rewrite `Convert::resolvePath()` and move to `File::resolve()`, deprecating the former

`ArrayMapper`:
- Rewrite as a self-contained class instead of a closure factory
- Remove its `Mapper` facade
- Refactor `Pipeline::throughKeyMap()`

`Uri`:
- Implement `JsonSerializable`
- Make distinction between undefined (`null`) and empty (`''`) values when converting to and from URI strings and arrays
- Move regular expressions from `RegularExpression` to `Uri`

`MultipleErrorException`:
- Add `MultipleErrorExceptionInterface` and move implementation from `MultipleErrorException` to `MultipleErrorExceptionTrait`
- Add `hasUnreportedErrors()` and `getMessageWithoutErrors()`
- In `Console::exception()`, use `hasUnreportedErrors()` and `getMessageWithoutErrors()` to ensure errors are only reported once

### Deprecated

- As above, deprecate:
  - `Convert::classToBasename()`
  - `Convert::classToNamespace()`
  - `Convert::parseUrl()`
  - `Convert::resolvePath()`
  - `Convert::resolveRelativeUrl()`
  - `Convert::unparseUrl()`

### Removed

- Remove redundant `FluentIterator` class (`IterableIterator` can be used or extended instead)

### Fixed

- Fix issue where `Convert::classToBasename()` removes suffixes from the middle of class names
- Fix issue where URI strings with an empty host are considered invalid
- Fix issue where URI objects with an empty host cannot have userinfo or port
- Fix issue where `file://` URIs are composed as `file:`
- Fix issue where errors passed to `MultipleErrorException` are not reported if `ErrorHandler` is not handling exceptions (e.g. when running PHPUnit) by adding errors to the exception message

## [v0.21.11] - 2023-12-08

### Added

- Allow exceptions to be thrown with an exit status
- In `ErrorHandler`, check unhandled exceptions for an exit status to return
- Add and implement `CliOptionValueType::PATH_OR_DASH` and `CliOptionValueType::FILE_OR_DASH`
- Add `CliOptionVisibility::SCHEMA` so options can be flagged for inclusion in a JSON Schema
- Add `CliOptionVisibility::ALL_EXCEPT_SYNOPSIS` for convenience
- Add `Json` class
- Add utility methods:
  - `Date::timezone()`, `Date::maybeSetTimezone()`
  - `File::is()`
  - `Pcre::quoteCharacterClass()`
  - `Str::wrap()`
  - `Test::isStringable()`

### Changed

- `ErrorHandler`: change default exit status from 15 to 16 to improve support for bitmasks as return values
- `Console`: indent fenced code blocks for separation from surrounding text
- Move and refactor, deprecating the former:
  - `Convert::splitWords()` -> `Str::toWords()`
  - `Convert::toSnakeCase()` -> `Str::toSnakeCase()`
  - `Convert::toKebabCase()` -> `Str::toKebabCase()`
  - `Convert::toCamelCase()` -> `Str::toCamelCase()`
  - `Convert::toPascalCase()` -> `Str::toPascalCase()`
- Review `Formatters`
  - Extend `Utility` and make methods `static`
  - Remove `Format` facade and rename `Formatters` to `Format`
  - Add `Format::value()`
  - Refactor `Format::date()` and `Format::dateRange()`

### Deprecated

- Deprecate `Convert::toTimezone()` (replaced with `Date::timezone()`)
- Deprecate `Test::areSameFile()` (replaced with `File::is()`)
- Deprecate case conversion methods (as above)

### Removed

- Remove support for `"t"` and `"f"` as boolean strings
- Remove previously deprecated methods
- Remove `Test::classImplements()`

### Fixed

- Fix `CliCommand` issue where `getOptionValues()` and `getDefaultOptionValues()` fail with an exception when a positional option's `$Long` property is `null`
- Fix `Console` issue where fenced code blocks are formatted as inline spans when indented

## [v0.21.10] - 2023-12-05

### Added

- Add `Str::coalesce()`
- Add `Arr::sameValues()`
- Add `RecursiveFilesystemIterator::count()`
- Add `Builder::unsetB()`

### Changed

- In `Builder` methods, only return a clone if a value is changed
- Rename `ProvidesBuilder` interface to `Buildable` and consolidate extended interfaces, reverting needlessly complicated split
- Add default implementation of `Buildable::getBuilder()` to `HasBuilder` trait and remove boilerplate code from classes that use it
- Allow assertions in `Assert` to throw a given exception
- Clean up and rename `Convert::toBoolOrNull()` and `toIntOrNull()` to `toBool()` and `toInt()`
- Clean up `Env` and adopt `Pcre::*` methods
- Tolerate whitespace around boolean and integer values in environment variables
- Clean up abstract enumeration and dictionary classes

`Cli`:
- Add `CliOption::$Name`
  - Primarily for `*_POSITIONAL` options, but others can use it too
  - Takes the value of `CliOption::$Long` if not set explicitly
  - Positional options are not permitted to apply different values to `CliOption::$Name` and `CliOption::$Long`
- Add `CliOption::$IsBound`
  - `true` if the option is bound to a variable via constructor parameter `$bindTo`
  - Prevents propagation of normalised values into the option's scope
- Add `CliOption::$Unique`
- Implement case-insensitive `ONE_OF_*` value matching

### Removed

- Remove support for extending default and/or environment values via `CliOption::$KeepDefault` and `CliOption::$KeepEnv`
- Remove unused `Convert::emptyToNull()` method
- Remove `ResolvesBuilder`, `ReturnsBuilder`, `ReturnsBuilderService` interfaces

### Fixed

- `Env`: fix issue where negative integers are rejected
- `Cli`: add explicit checks for `null`, `''` and `[]` to prevent issues with "falsey" values like `"0"`
- Fix `Introspector` issues:
  - Detect constructor parameters that have a default value but are not nullable, and throw an exception if `null` is passed to them, e.g. from a builder
  - Determine minimum number of arguments to pass to a constructor and suppress unnecessary arguments, e.g. so classes can rely on `func_num_args()` to detect variables passed by reference

## [v0.21.9] - 2023-11-30

### Added

- Add `ICollection::empty()`
- Add `Arr::of()`, `Arr::isListOf()`

### Changed

- Rename:
  - `Arr::listOfArrayKey()` -> `Arr::isListOfArrayKey()`
  - `Arr::listOfInt()` -> `Arr::isListOfInt()`
  - `Arr::listOfString()` -> `Arr::isListOfString()`

### Deprecated

- Deprecate `Test::isArrayOf()` (replaced with `Arr::of()`)

### Fixed

- `Cli`: fix issue where empty default values are displayed in help messages
- `Cli`: remove escapes from horizontal whitespace to fix issue where help written as Markdown wraps weirdly when rendered

## [v0.21.8] - 2023-11-29

### Added

- Add `HttpHeaders` and `IHttpHeaders`
- Add `IAccessToken` and implement its methods in `AccessToken`
- Add `Jsonable`
- Add `Arr::lower()`, `Arr::upper()`, `Arr::toIndex()`
- Add `File::write()`, `File::seek()`, `File::tell()`
- Add `Pcre::grep()`

### Changed

- `ICollection`:

  - Allow callbacks to receive item keys, values or both
  - Limit `TKey` to `array-key`, removing hypothetical support for arbitrary key types but allowing implementation of `Arrayable` etc.
  - Implement `Arrayable`, `Jsonable`, `JsonSerializable`
  - In `ICollection::merge()`, accept `Arrayable|iterable` instead of `static|iterable`

- Move parts of `TCollection` to a separate `TReadableCollection` trait
- Move `Lkrms\Support\Http` -> `Lkrms\Http`
- Move `Lkrms\Support\Catalog\Http*` -> `Lkrms\Http\Catalog`
- Move `Lkrms\Auth` -> `Lkrms\Http\Auth`
- Add `HttpHeaderGroup` and clean up existing `Http` enumerations
- Rename `HttpRequestMethods` -> `HttpRequestMethodGroup`
- Rename `Curler::addPrivateHeaderName()` -> `addSensitiveHeaderName()`
- Replace `CurlerHeaders` and friends with `HttpHeaders` and friends
- Replace `ICurlerHeaders` calls with `IHttpHeaders` equivalents:

  - `getHeaders()` -> `getLines()`
  - `getHeaderValue()` -> `getHeaderLine()`
  - `getHeaderValues()` -> `getHeaderLines()`

- In `File::close()`, make `$filename` optional and rename it to `$uri`
- In `File::writeCsv()`, use `php://temp` instead of `php://memory` for temporary output
- Rename `Regex::NOT_ESCAPED` -> `Regex::BEFORE_UNESCAPED`
- Make `FilesystemErrorException` (and most other exceptions) extend `RuntimeException` instead of `Exception`

### Removed

- Remove superseded `ICurlerHeaders`, `CurlerHeaders`, `CurlerHeadersFlag`, `CurlerHeader`
- Remove unused `FluentArray` class
- Remove `$private` arguments from `Curler::addHeader()` and `Curler::setHeader()`
- Remove support for filtering headers by pattern in `Curler::unsetHeader()`
- Remove `$value` parameter from `ICollection::unset()`

### Fixed

- Fix issue where `File::getStreamUri()` fails for streams with no URI

## [v0.21.7] - 2023-11-23

### Added

- Add `Arr::extend()`
- Sync: add `SyncProvider::pipelineFrom()` and `pipelineTo()` to simplify creation of entity pipelines that satisfy static analysis

### Changed

- **In `Cache::get()`, return `null` instead of `false` when an item has expired or doesn't exist**
- In `OAuth2Client`, extend the scope of the most recently issued token if possible
- Make `OAuth2Client::flushTokens()` and `OAuth2Client::getIdToken()` public
- Move `OAuth2Flow` from `Auth\Catalog` to `Auth` namespace

### Deprecated

- Deprecate `SyncProvider::pipeline()` and `callbackPipeline()`

### Fixed

- Fix OAuth 2.0 issue where explicitly requested scopes are not propagated

## [v0.21.6] - 2023-11-22

### Changed

- Allow non-immutable collections and lists to be explicitly cloned via a public `clone()` method

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

[v0.99.50]: https://github.com/salient-labs/toolkit/compare/v0.99.49...v0.99.50
[v0.99.49]: https://github.com/salient-labs/toolkit/compare/v0.99.48...v0.99.49
[v0.99.48]: https://github.com/salient-labs/toolkit/compare/v0.99.47...v0.99.48
[v0.99.47]: https://github.com/salient-labs/toolkit/compare/v0.99.46...v0.99.47
[v0.99.46]: https://github.com/salient-labs/toolkit/compare/v0.99.45...v0.99.46
[v0.99.45]: https://github.com/salient-labs/toolkit/compare/v0.99.44...v0.99.45
[v0.99.44]: https://github.com/salient-labs/toolkit/compare/v0.99.43...v0.99.44
[v0.99.43]: https://github.com/salient-labs/toolkit/compare/v0.99.42...v0.99.43
[v0.99.42]: https://github.com/salient-labs/toolkit/compare/v0.99.41...v0.99.42
[v0.99.41]: https://github.com/salient-labs/toolkit/compare/v0.99.40...v0.99.41
[v0.99.40]: https://github.com/salient-labs/toolkit/compare/v0.99.39...v0.99.40
[v0.99.39]: https://github.com/salient-labs/toolkit/compare/v0.99.38...v0.99.39
[v0.99.38]: https://github.com/salient-labs/toolkit/compare/v0.99.37...v0.99.38
[v0.99.37]: https://github.com/salient-labs/toolkit/compare/v0.99.36...v0.99.37
[v0.99.36]: https://github.com/salient-labs/toolkit/compare/v0.99.35...v0.99.36
[v0.99.35]: https://github.com/salient-labs/toolkit/compare/v0.99.34...v0.99.35
[v0.99.34]: https://github.com/salient-labs/toolkit/compare/v0.99.33...v0.99.34
[v0.99.33]: https://github.com/salient-labs/toolkit/compare/v0.99.32...v0.99.33
[v0.99.32]: https://github.com/salient-labs/toolkit/compare/v0.99.31...v0.99.32
[v0.99.31]: https://github.com/salient-labs/toolkit/compare/v0.99.30...v0.99.31
[v0.99.30]: https://github.com/salient-labs/toolkit/compare/v0.99.29...v0.99.30
[v0.99.29]: https://github.com/salient-labs/toolkit/compare/v0.99.28...v0.99.29
[v0.99.28]: https://github.com/salient-labs/toolkit/compare/v0.99.27...v0.99.28
[v0.99.27]: https://github.com/salient-labs/toolkit/compare/v0.99.26...v0.99.27
[v0.99.26]: https://github.com/salient-labs/toolkit/compare/v0.99.25...v0.99.26
[v0.99.25]: https://github.com/salient-labs/toolkit/compare/v0.99.24...v0.99.25
[v0.99.24]: https://github.com/salient-labs/toolkit/compare/v0.99.23...v0.99.24
[v0.99.23]: https://github.com/salient-labs/toolkit/compare/v0.99.22...v0.99.23
[v0.99.22]: https://github.com/salient-labs/toolkit/compare/v0.99.21...v0.99.22
[v0.99.21]: https://github.com/salient-labs/toolkit/compare/v0.99.20...v0.99.21
[v0.99.20]: https://github.com/salient-labs/toolkit/compare/v0.99.19...v0.99.20
[v0.99.19]: https://github.com/salient-labs/toolkit/compare/v0.99.18...v0.99.19
[v0.99.18]: https://github.com/salient-labs/toolkit/compare/v0.99.17...v0.99.18
[v0.99.17]: https://github.com/salient-labs/toolkit/compare/v0.99.16...v0.99.17
[v0.99.16]: https://github.com/salient-labs/toolkit/compare/v0.99.15...v0.99.16
[v0.99.15]: https://github.com/salient-labs/toolkit/compare/v0.99.14...v0.99.15
[v0.99.14]: https://github.com/salient-labs/toolkit/compare/v0.99.13...v0.99.14
[v0.99.13]: https://github.com/salient-labs/toolkit/compare/v0.99.12...v0.99.13
[v0.99.12]: https://github.com/salient-labs/toolkit/compare/v0.99.11...v0.99.12
[v0.99.11]: https://github.com/salient-labs/toolkit/compare/v0.99.10...v0.99.11
[v0.99.10]: https://github.com/salient-labs/toolkit/compare/v0.99.9...v0.99.10
[v0.99.9]: https://github.com/salient-labs/toolkit/compare/v0.99.8...v0.99.9
[v0.99.8]: https://github.com/salient-labs/toolkit/compare/v0.99.7...v0.99.8
[v0.99.7]: https://github.com/salient-labs/toolkit/compare/v0.99.6...v0.99.7
[v0.99.6]: https://github.com/salient-labs/toolkit/compare/v0.99.5...v0.99.6
[v0.99.5]: https://github.com/salient-labs/toolkit/compare/v0.99.4...v0.99.5
[v0.99.4]: https://github.com/salient-labs/toolkit/compare/v0.99.3...v0.99.4
[v0.99.3]: https://github.com/salient-labs/toolkit/compare/v0.99.2...v0.99.3
[v0.99.2]: https://github.com/salient-labs/toolkit/compare/v0.99.1...v0.99.2
[v0.99.1]: https://github.com/salient-labs/toolkit/compare/v0.99.0...v0.99.1
[v0.99.0]: https://github.com/salient-labs/toolkit/compare/v0.21.49...v0.99.0
[v0.21.49]: https://github.com/salient-labs/toolkit/compare/v0.21.48...v0.21.49
[v0.21.48]: https://github.com/salient-labs/toolkit/compare/v0.21.47...v0.21.48
[v0.21.47]: https://github.com/salient-labs/toolkit/compare/v0.21.46...v0.21.47
[v0.21.46]: https://github.com/salient-labs/toolkit/compare/v0.21.45...v0.21.46
[v0.21.45]: https://github.com/salient-labs/toolkit/compare/v0.21.44...v0.21.45
[v0.21.44]: https://github.com/salient-labs/toolkit/compare/v0.21.43...v0.21.44
[v0.21.43]: https://github.com/salient-labs/toolkit/compare/v0.21.42...v0.21.43
[v0.21.42]: https://github.com/salient-labs/toolkit/compare/v0.21.41...v0.21.42
[v0.21.41]: https://github.com/salient-labs/toolkit/compare/v0.21.40...v0.21.41
[v0.21.40]: https://github.com/salient-labs/toolkit/compare/v0.21.39...v0.21.40
[v0.21.39]: https://github.com/salient-labs/toolkit/compare/v0.21.38...v0.21.39
[v0.21.38]: https://github.com/salient-labs/toolkit/compare/v0.21.37...v0.21.38
[v0.21.37]: https://github.com/salient-labs/toolkit/compare/v0.21.36...v0.21.37
[v0.21.36]: https://github.com/salient-labs/toolkit/compare/v0.21.35...v0.21.36
[v0.21.35]: https://github.com/salient-labs/toolkit/compare/v0.21.34...v0.21.35
[v0.21.34]: https://github.com/salient-labs/toolkit/compare/v0.21.33...v0.21.34
[v0.21.33]: https://github.com/salient-labs/toolkit/compare/v0.21.32...v0.21.33
[v0.21.32]: https://github.com/salient-labs/toolkit/compare/v0.21.31...v0.21.32
[v0.21.31]: https://github.com/salient-labs/toolkit/compare/v0.21.30...v0.21.31
[v0.21.30]: https://github.com/salient-labs/toolkit/compare/v0.21.29...v0.21.30
[v0.21.29]: https://github.com/salient-labs/toolkit/compare/v0.21.28...v0.21.29
[v0.21.28]: https://github.com/salient-labs/toolkit/compare/v0.21.27...v0.21.28
[v0.21.27]: https://github.com/salient-labs/toolkit/compare/v0.21.26...v0.21.27
[v0.21.26]: https://github.com/salient-labs/toolkit/compare/v0.21.25...v0.21.26
[v0.21.25]: https://github.com/salient-labs/toolkit/compare/v0.21.24...v0.21.25
[v0.21.24]: https://github.com/salient-labs/toolkit/compare/v0.21.23...v0.21.24
[v0.21.23]: https://github.com/salient-labs/toolkit/compare/v0.21.22...v0.21.23
[v0.21.22]: https://github.com/salient-labs/toolkit/compare/v0.21.21...v0.21.22
[v0.21.21]: https://github.com/salient-labs/toolkit/compare/v0.21.20...v0.21.21
[v0.21.20]: https://github.com/salient-labs/toolkit/compare/v0.21.19...v0.21.20
[v0.21.19]: https://github.com/salient-labs/toolkit/compare/v0.21.18...v0.21.19
[v0.21.18]: https://github.com/salient-labs/toolkit/compare/v0.21.17...v0.21.18
[v0.21.17]: https://github.com/salient-labs/toolkit/compare/v0.21.16...v0.21.17
[v0.21.16]: https://github.com/salient-labs/toolkit/compare/v0.21.15...v0.21.16
[v0.21.15]: https://github.com/salient-labs/toolkit/compare/v0.21.14...v0.21.15
[v0.21.14]: https://github.com/salient-labs/toolkit/compare/v0.21.13...v0.21.14
[v0.21.13]: https://github.com/salient-labs/toolkit/compare/v0.21.12...v0.21.13
[v0.21.12]: https://github.com/salient-labs/toolkit/compare/v0.21.11...v0.21.12
[v0.21.11]: https://github.com/salient-labs/toolkit/compare/v0.21.10...v0.21.11
[v0.21.10]: https://github.com/salient-labs/toolkit/compare/v0.21.9...v0.21.10
[v0.21.9]: https://github.com/salient-labs/toolkit/compare/v0.21.8...v0.21.9
[v0.21.8]: https://github.com/salient-labs/toolkit/compare/v0.21.7...v0.21.8
[v0.21.7]: https://github.com/salient-labs/toolkit/compare/v0.21.6...v0.21.7
[v0.21.6]: https://github.com/salient-labs/toolkit/compare/v0.21.5...v0.21.6
[v0.21.5]: https://github.com/salient-labs/toolkit/compare/v0.21.4...v0.21.5
[v0.21.4]: https://github.com/salient-labs/toolkit/compare/v0.21.3...v0.21.4
[v0.21.3]: https://github.com/salient-labs/toolkit/compare/v0.21.2...v0.21.3
[v0.21.2]: https://github.com/salient-labs/toolkit/compare/v0.21.1...v0.21.2
[v0.21.1]: https://github.com/salient-labs/toolkit/compare/v0.21.0...v0.21.1
[v0.21.0]: https://github.com/salient-labs/toolkit/compare/v0.20.89...v0.21.0
[v0.20.89]: https://github.com/salient-labs/toolkit/compare/v0.20.88...v0.20.89
[v0.20.88]: https://github.com/salient-labs/toolkit/compare/v0.20.87...v0.20.88
[v0.20.87]: https://github.com/salient-labs/toolkit/compare/v0.20.86...v0.20.87
[v0.20.86]: https://github.com/salient-labs/toolkit/compare/v0.20.85...v0.20.86
[v0.20.85]: https://github.com/salient-labs/toolkit/compare/v0.20.84...v0.20.85
[v0.20.84]: https://github.com/salient-labs/toolkit/compare/v0.20.83...v0.20.84
[v0.20.83]: https://github.com/salient-labs/toolkit/compare/v0.20.82...v0.20.83
[v0.20.82]: https://github.com/salient-labs/toolkit/compare/v0.20.81...v0.20.82
[v0.20.81]: https://github.com/salient-labs/toolkit/compare/v0.20.80...v0.20.81
[v0.20.80]: https://github.com/salient-labs/toolkit/compare/v0.20.79...v0.20.80
[v0.20.79]: https://github.com/salient-labs/toolkit/compare/v0.20.78...v0.20.79
[v0.20.78]: https://github.com/salient-labs/toolkit/compare/v0.20.77...v0.20.78
[v0.20.77]: https://github.com/salient-labs/toolkit/compare/v0.20.76...v0.20.77
[v0.20.76]: https://github.com/salient-labs/toolkit/compare/v0.20.75...v0.20.76
[v0.20.75]: https://github.com/salient-labs/toolkit/compare/v0.20.74...v0.20.75
[v0.20.74]: https://github.com/salient-labs/toolkit/compare/v0.20.73...v0.20.74
[v0.20.73]: https://github.com/salient-labs/toolkit/compare/v0.20.72...v0.20.73
[v0.20.72]: https://github.com/salient-labs/toolkit/compare/v0.20.71...v0.20.72
[v0.20.71]: https://github.com/salient-labs/toolkit/compare/v0.20.70...v0.20.71
[v0.20.70]: https://github.com/salient-labs/toolkit/compare/v0.20.69...v0.20.70
[v0.20.69]: https://github.com/salient-labs/toolkit/compare/v0.20.68...v0.20.69
[v0.20.68]: https://github.com/salient-labs/toolkit/compare/v0.20.67...v0.20.68
[v0.20.67]: https://github.com/salient-labs/toolkit/compare/v0.20.66...v0.20.67
[v0.20.66]: https://github.com/salient-labs/toolkit/compare/v0.20.65...v0.20.66
[v0.20.65]: https://github.com/salient-labs/toolkit/compare/v0.20.64...v0.20.65
[v0.20.64]: https://github.com/salient-labs/toolkit/compare/v0.20.63...v0.20.64
[v0.20.63]: https://github.com/salient-labs/toolkit/compare/v0.20.62...v0.20.63
[v0.20.62]: https://github.com/salient-labs/toolkit/compare/v0.20.61...v0.20.62
[v0.20.61]: https://github.com/salient-labs/toolkit/compare/v0.20.60...v0.20.61
[v0.20.60]: https://github.com/salient-labs/toolkit/compare/v0.20.59...v0.20.60
[v0.20.59]: https://github.com/salient-labs/toolkit/compare/v0.20.58...v0.20.59
[v0.20.58]: https://github.com/salient-labs/toolkit/compare/v0.20.57...v0.20.58
[v0.20.57]: https://github.com/salient-labs/toolkit/compare/v0.20.56...v0.20.57
[v0.20.56]: https://github.com/salient-labs/toolkit/compare/v0.20.55...v0.20.56
[v0.20.55]: https://github.com/salient-labs/toolkit/compare/v0.20.54...v0.20.55
[v0.20.54]: https://github.com/salient-labs/toolkit/releases/tag/v0.20.54
