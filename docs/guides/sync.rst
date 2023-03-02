Syncing data between backends
=============================

SyncProvider
------------

Operation signatures
~~~~~~~~~~~~~~~~~~~~

To perform a sync operation, an `ISyncProvider`_ (extending `SyncProvider`_ is
recommended) must implement an `entity`_’s provider interface (e.g.
``\Provider\UserProvider`` for a ``\User`` entity) and either:

1. return a closure for the `SyncOperation`_ and entity via `getDefinition()`_,
   or
2. declare a method using the naming convention below.

In either case, the correct signature for the implemented operation must be
used. The first value passed is always the current `ISyncContext`_ and
**optional** arguments may be accepted after mandatory parameters.

+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| Operation [1]_       | Closure signature                                                       | Equivalent method [2]_     | Alternative method [3]_ |
+======================+=========================================================================+============================+=========================+
| ``CREATE``           | ``fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity``   | ``create<EntitySingular>`` | ``create_<Entity>``     |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``READ``             | ``fn(ISyncContext $ctx, int\|string\|null $id, ...$args): ISyncEntity`` | ``get<EntitySingular>``    | ``get_<Entity>``        |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``UPDATE``           | ``fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity``   | ``update<EntitySingular>`` | ``update_<Entity>``     |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``DELETE``           | ``fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity``   | ``delete<EntitySingular>`` | ``delete_<Entity>``     |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``CREATE_LIST``      | ``fn(ISyncContext $ctx, iterable $entities, ...$args): iterable``       | ``create<EntityPlural>``   | ``createList_<Entity>`` |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``READ_LIST``\  [4]_ | ``fn(ISyncContext $ctx, ...$args): iterable``                           | ``get<EntityPlural>``      | ``getList_<Entity>``    |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``UPDATE_LIST``      | ``fn(ISyncContext $ctx, iterable $entities, ...$args): iterable``       | ``update<EntityPlural>``   | ``updateList_<Entity>`` |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+
| ``DELETE_LIST``      | ``fn(ISyncContext $ctx, iterable $entities, ...$args): iterable``       | ``delete<EntityPlural>``   | ``deleteList_<Entity>`` |
+----------------------+-------------------------------------------------------------------------+----------------------------+-------------------------+

.. [1]
   See `SyncOperation`_.

.. [2]
   Method names must match either the singular or plural form of the entity’s
   unqualified name.

.. [3]
   Recommended when the singular and plural forms of a class name are the same.
   Method names must match the entity’s unqualified name.

.. [4]
   See `ISyncContext::withArgs()`_ for filter argument recommendations,
   including recognised signatures.

.. _ISyncProvider: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Contract-ISyncProvider.html
.. _SyncProvider: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Concept-SyncProvider.html
.. _entity: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Contract-ISyncEntity.html
.. _SyncOperation: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Support-SyncOperation.html
.. _getDefinition(): https://lkrms.github.io/php-util/classes/Lkrms-Sync-Contract-ISyncProvider.html#method_getDefinition
.. _ISyncContext: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Contract-ISyncContext.html
.. _`ISyncContext::withArgs()`: https://lkrms.github.io/php-util/classes/Lkrms-Sync-Contract-ISyncContext.html#method_withArgs
