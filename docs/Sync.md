## Syncing data between backends

### Sync providers

A sync provider is a class that implements [ISyncProvider][] and performs one or
more [sync operations][SyncOperation] on supported [entity classes][ISyncEntity]
to propagate data to and from a backend.

For a provider to perform sync operations on entities of a given type, it must
also implement the entity's provider interface. Aside from entities in
namespaces registered with a [class resolver][ISyncClassResolver], the provider
interface is assumed to be:

```
<entity-namespace>\Provider\<entity>Provider
```

A provider would be expected to service `Acme\Sync\User` entities if it
implemented the `Acme\Sync\Provider\UserProvider` interface, for example.

The entity provider interface doesn't need to have declared methods (although
entities can be serviced this way if needed), but it does need to exist.

`lk-util` makes it easy to generate a provider interface:

```shell
vendor/bin/lk-util generate sync provider --magic --op 'get,get-list' 'Acme\Sync\User'
```

#### Class resolvers

To map entity classes to different provider interfaces (or multiple entities to
one interface, perhaps), you can provide an [ISyncClassResolver][] to the entity
store when registering a namespace. See [Sync::namespace()][namespace] for
details and [this test fixture][SyncClassResolver.php] for a working example
that would map `Acme\Sync\Entity\User` to `Acme\Sync\Contract\ProvidesUser`.

#### Operations

To perform a sync operation on an [entity][ISyncEntity], an [ISyncProvider][]
must implement its provider interface and either:

1. return a closure for the [SyncOperation][] and entity via
   [getDefinition()][getDefinition], or
2. declare a method for the operation using the naming convention below.

In either case, the signature for the implemented operation must be as follows.
The first value passed is always the current [ISyncContext] and **optional**
arguments may be accepted after mandatory parameters.

| Operation[^op]  | Closure signature                                                     | Equivalent method[^1]    | Alternative method[^2] |
| --------------- | --------------------------------------------------------------------- | ------------------------ | ---------------------- |
| `CREATE`        | `fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity`   | `create<EntitySingular>` | `create_<Entity>`      |
| `READ`          | `fn(ISyncContext $ctx, int\|string\|null $id, ...$args): ISyncEntity` | `get<EntitySingular>`    | `get_<Entity>`         |
| `UPDATE`        | `fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity`   | `update<EntitySingular>` | `update_<Entity>`      |
| `DELETE`        | `fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity`   | `delete<EntitySingular>` | `delete_<Entity>`      |
| `CREATE_LIST`   | `fn(ISyncContext $ctx, iterable $entities, ...$args): iterable`       | `create<EntityPlural>`   | `createList_<Entity>`  |
| `READ_LIST`[^3] | `fn(ISyncContext $ctx, ...$args): iterable`                           | `get<EntityPlural>`      | `getList_<Entity>`     |
| `UPDATE_LIST`   | `fn(ISyncContext $ctx, iterable $entities, ...$args): iterable`       | `update<EntityPlural>`   | `updateList_<Entity>`  |
| `DELETE_LIST`   | `fn(ISyncContext $ctx, iterable $entities, ...$args): iterable`       | `delete<EntityPlural>`   | `deleteList_<Entity>`  |

[^op]: See [SyncOperation].
[^1]:
    Method names must match either the singular or plural form of the entity's
    unqualified name.

[^2]:
    Recommended when the singular and plural forms of a class name are the same.
    Method names must match the entity's unqualified name.

[^3]:
    See [ISyncContext::withArgs()][withArgs] for filter argument
    recommendations, including recognised signatures.

[getDefinition]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Contract.ISyncProvider.html#_getDefinition
[ISyncContext]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Contract.ISyncContext.html
[ISyncEntity]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Contract.ISyncEntity.html
[ISyncProvider]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Contract.ISyncProvider.html
[ISyncClassResolver]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Contract.ISyncClassResolver.html
[SyncOperation]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Catalog.SyncOperation.html
[withArgs]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Contract.ISyncContext.html#_withArgs
[namespace]:
  https://lkrms.github.io/php-util/Lkrms.Sync.Support.SyncStore.html#_namespace
[SyncClassResolver.php]: ../tests/fixtures/Sync/SyncClassResolver.php
