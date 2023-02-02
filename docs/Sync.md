## Syncing data between backends

### SyncProvider

#### Operation signatures

To perform a sync operation, a [SyncProvider][SyncProvider] must implement an
[entity][SyncEntity]'s provider interface (e.g. `\Provider\UserProvider` for a
`\User` entity) and either:

1. return a closure for the [SyncOperation][SyncOperation] and entity via
   [getDefinition()][getDefinition], or
2. declare a method using the naming convention below.

In either case, the correct signature for the implemented operation must be
used. The first value passed is always the current [SyncContext][SyncContext]
and **optional** arguments may be accepted after mandatory parameters.

| Operation[^op]  | Closure signature                                                   | Equivalent method[^1]    | Alternative method[^2] |
| --------------- | ------------------------------------------------------------------- | ------------------------ | ---------------------- |
| `CREATE`        | `fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity`    | `create<EntitySingular>` | `create_<Entity>`      |
| `READ`          | `fn(SyncContext $ctx, int\|string\|null $id, ...$args): SyncEntity` | `get<EntitySingular>`    | `get_<Entity>`         |
| `UPDATE`        | `fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity`    | `update<EntitySingular>` | `update_<Entity>`      |
| `DELETE`        | `fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity`    | `delete<EntitySingular>` | `delete_<Entity>`      |
| `CREATE_LIST`   | `fn(SyncContext $ctx, iterable $entities, ...$args): iterable`      | `create<EntityPlural>`   | `createList_<Entity>`  |
| `READ_LIST`[^3] | `fn(SyncContext $ctx, ...$args): iterable`                          | `get<EntityPlural>`      | `getList_<Entity>`     |
| `UPDATE_LIST`   | `fn(SyncContext $ctx, iterable $entities, ...$args): iterable`      | `update<EntityPlural>`   | `updateList_<Entity>`  |
| `DELETE_LIST`   | `fn(SyncContext $ctx, iterable $entities, ...$args): iterable`      | `delete<EntityPlural>`   | `deleteList_<Entity>`  |

[^op]: See [SyncOperation][SyncOperation].

[^1]: Method names must match either the singular or plural form of the entity's
    unqualified name.

[^2]: Recommended when the singular and plural forms of a class name are the
    same. Method names must match the entity's unqualified name.

[^3]: See [ISyncContext::withArgs()][withArgs] for filter argument
    recommendations, including recognised signatures.


[getDefinition]: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Concept-SyncProvider.html#method_getDefinition
[SyncContext]: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Support-SyncContext.html
[SyncEntity]: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Concept-SyncEntity.html
[SyncOperation]: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Support-SyncOperation.html
[SyncProvider]: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Concept-SyncProvider.html
[withArgs]: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Contract-ISyncContext.html#method_withArgs

