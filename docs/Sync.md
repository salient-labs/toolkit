## Syncing data between backends

### Sync providers

A sync provider is a class that implements [`SyncProviderInterface`][provider]
to propagate data to and from a backend, e.g. an API or database.

For a provider to perform [sync operations][operation] on an [entity][] of a
given type, it must also implement the entity's provider interface, which--aside
from entities with a registered [namespace helper][]--has the following name:

```
<entity_namespace>\Provider\<entity>Provider
```

A provider servicing `Acme\Sync\User` entities would need to implement the
`Acme\Sync\Provider\UserProvider` interface, for example.

Entity provider interfaces must extend [`SyncProviderInterface`][provider]. They
don't need to have declared methods, but sync operations can be implemented as
declared methods if desired.

`sli` makes it easy to generate a provider interface for an entity:

```shell
vendor/bin/sli generate sync provider --magic --op 'get,get-list' 'Acme\Sync\User'
```

#### Namespace helpers

To map entity classes to different provider interfaces (or multiple entities to
one interface, perhaps), you can provide a [namespace helper][] to the entity
store when registering a namespace. See
[`registerNamespace()`][registerNamespace] for details and
[SyncNamespaceHelper.php][] for a working example that maps
`Acme\Sync\Entity\User` to `Acme\Sync\Contract\ProvidesUser`.

#### Operations

To perform a sync operation on an [entity][], a [provider][] must implement its
provider interface and either:

1. return a closure for the [operation][] and entity via
   [`getDefinition()`][getDefinition], or
2. declare a method for the operation using the naming convention below.

In either case, the signature for the implemented operation must be as follows.
The first value passed is always the current [context][] and **optional**
arguments may be accepted after mandatory parameters.

| Operation[^op]  | Closure signature                                                                           | Equivalent method[^1]    | Alternative method[^2] |
| --------------- | ------------------------------------------------------------------------------------------- | ------------------------ | ---------------------- |
| `CREATE`        | `fn(SyncContextInterface $ctx, SyncEntityInterface $entity, ...$args): SyncEntityInterface` | `create<EntitySingular>` | `create_<Entity>`      |
| `READ`          | `fn(SyncContextInterface $ctx, int\|string\|null $id, ...$args): SyncEntityInterface`       | `get<EntitySingular>`    | `get_<Entity>`         |
| `UPDATE`        | `fn(SyncContextInterface $ctx, SyncEntityInterface $entity, ...$args): SyncEntityInterface` | `update<EntitySingular>` | `update_<Entity>`      |
| `DELETE`        | `fn(SyncContextInterface $ctx, SyncEntityInterface $entity, ...$args): SyncEntityInterface` | `delete<EntitySingular>` | `delete_<Entity>`      |
| `CREATE_LIST`   | `fn(SyncContextInterface $ctx, iterable $entities, ...$args): iterable`                     | `create<EntityPlural>`   | `createList_<Entity>`  |
| `READ_LIST`[^3] | `fn(SyncContextInterface $ctx, ...$args): iterable`                                         | `get<EntityPlural>`      | `getList_<Entity>`     |
| `UPDATE_LIST`   | `fn(SyncContextInterface $ctx, iterable $entities, ...$args): iterable`                     | `update<EntityPlural>`   | `updateList_<Entity>`  |
| `DELETE_LIST`   | `fn(SyncContextInterface $ctx, iterable $entities, ...$args): iterable`                     | `delete<EntityPlural>`   | `deleteList_<Entity>`  |

[^op]: See [`SyncOperation`][operation].
[^1]:
    Method names must match either the singular or plural form of the entity's
    unqualified name.

[^2]:
    Recommended when the singular and plural forms of a class name are the same.
    Method names must match the entity's unqualified name.

[^3]: See [`withArgs()`][withArgs] for recognised signatures.

#### Contexts

Sync operations are performed within an immutable [context][] created by the
[provider][] and replaced as needed to reflect changes to configuration and
state. Contents include:

| Description            | Getter(s)                                   | Setter(s)                    | Notes                                                      |
| ---------------------- | ------------------------------------------- | ---------------------------- | ---------------------------------------------------------- |
| Provider               | `getProvider()`                             | -                            |                                                            |
| Service container      | `getContainer()`                            | `withContainer()`            |                                                            |
| Array key conformity   | `getConformity()`                           | `withConformity()`           |                                                            |
| Entities               | `getEntities()`, `getLastEntity()`          | `pushEntity()`               | Tracks nested entity scope. See `recursionDetected()`.     |
| Parent entity          | `getParent()`                               | `withParent()`               | [`Treeable`][treeable] entities only.                      |
| Arbitrary values       | `hasValue()`, `getValue()`                  | `withValue()`                |                                                            |
| Filter values          | `hasFilter()`,`getFilter()`, `getFilters()` | `withArgs()`                 | Derived from non-mandatory arguments. See `claimFilter()`. |
| Filter policy callback | `applyFilterPolicy()`                       | `withFilterPolicyCallback()` |                                                            |
| Deferral policy        | `getDeferralPolicy()`                       | `withDeferralPolicy()`       | Applies to nested entity retrieval.                        |
| Hydration policy       | `getHydrationPolicy()`                      | `withHydrationPolicy()`      | Applies to entity relationship retrieval.                  |
| Offline mode           | `getOffline()`                              | `withOffline()`              |                                                            |

[context]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncContextInterface.html
[entity]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncEntityInterface.html
[getDefinition]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncProviderInterface.html#_getDefinition
[namespace helper]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncNamespaceHelperInterface.html
[operation]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncOperation.html
[provider]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncProviderInterface.html
[registerNamespace]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncStoreInterface.html#_registerNamespace
[SyncNamespaceHelper.php]:
  ../tests/fixtures/Toolkit/Sync/SyncNamespaceHelper.php
[treeable]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Core.Treeable.html
[withArgs]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncContextInterface.html#_withArgs
