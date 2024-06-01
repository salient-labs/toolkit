## Syncing data between backends

### Sync providers

A sync provider is a class that implements [SyncProviderInterface][] and
performs one or more [sync operations][SyncOperation] on supported [entity
classes][SyncEntityInterface] to propagate data to and from a backend.

For a provider to perform sync operations on entities of a given type, it must
also implement the entity's provider interface, which--aside from entities in
namespaces with a registered [class resolver][SyncClassResolverInterface]--has
the following name:

```
<entity-namespace>\Provider\<entity>Provider
```

A provider would be expected to service `Acme\Sync\User` entities if it
implemented the `Acme\Sync\Provider\UserProvider` interface, for example.

The entity provider interface doesn't need to have declared methods (although
entities can be serviced this way if needed), but it does need to exist.

`sli` makes it easy to generate a provider interface:

```shell
vendor/bin/sli generate sync provider --magic --op 'get,get-list' 'Acme\Sync\User'
```

#### Class resolvers

To map entity classes to different provider interfaces (or multiple entities to
one interface, perhaps), you can provide a [SyncClassResolverInterface][] to the
entity store when registering a namespace. See [registerNamespace()][] for
details and [this test fixture][SyncClassResolver.php] for a working example
that maps `Acme\Sync\Entity\User` to `Acme\Sync\Contract\ProvidesUser`.

#### Operations

To perform a sync operation on an [entity][SyncEntityInterface], a
[SyncProviderInterface][] must implement its provider interface and either:

1. return a closure for the [SyncOperation][] and entity via
   [getDefinition()][], or
2. declare a method for the operation using the naming convention below.

In either case, the signature for the implemented operation must be as follows.
The first value passed is always the current [SyncContextInterface][] and
**optional** arguments may be accepted after mandatory parameters.

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

[^op]: See [SyncOperation][].
[^1]:
    Method names must match either the singular or plural form of the entity's
    unqualified name.

[^2]:
    Recommended when the singular and plural forms of a class name are the same.
    Method names must match the entity's unqualified name.

[^3]:
    See [SyncContextInterface::withFilter()][] for filter argument
    recommendations, including recognised signatures.

[getDefinition()]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncProviderInterface.html#_getDefinition
[registerNamespace()]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncStoreInterface.html#_registerNamespace
[SyncClassResolver.php]: ../tests/fixtures/Toolkit/Sync/SyncClassResolver.php
[SyncClassResolverInterface]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncClassResolverInterface.html
[SyncContextInterface::withFilter()]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncContextInterface.html#_withFilter
[SyncContextInterface]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncContextInterface.html
[SyncEntityInterface]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncEntityInterface.html
[SyncOperation]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncOperation.html
[SyncProviderInterface]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Sync.SyncProviderInterface.html
