<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\IntrospectionClass;
use Salient\Sync\AbstractSyncProvider;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use Closure;
use ReflectionClass;

/**
 * Cacheable class data shared between SyncIntrospectors
 *
 * @template TClass of object
 *
 * @extends IntrospectionClass<TClass>
 */
final class SyncIntrospectionClass extends IntrospectionClass
{
    /** @var bool|null */
    public $IsSyncEntity;
    /** @var bool|null */
    public $IsSyncProvider;

    // Related to `SyncEntityInterface`:

    /** @var string */
    public $EntityNoun;

    /**
     * Not set if the plural class name is the same as the singular one
     *
     * @var string|null
     */
    public $EntityPlural;

    // Related to `SyncProviderInterface`:

    /**
     * Interfaces that extend SyncProviderInterface
     *
     * @var array<class-string<SyncProviderInterface>>
     */
    public $SyncProviderInterfaces = [];

    /**
     * Entities serviced by SyncProviderInterface interfaces
     *
     * @var array<class-string<SyncEntityInterface>>
     */
    public $SyncProviderEntities = [];

    /**
     * Unambiguous kebab-case entity basename => entity
     *
     * @var array<string,class-string<SyncEntityInterface>>
     */
    public $SyncProviderEntityBasenames = [];

    /**
     * Lowercase method name => sync operation method declared by the provider
     *
     * @var array<string,string>
     */
    public $SyncOperationMethods = [];

    /**
     * Lowercase "magic" sync operation method => [ sync operation, entity ]
     *
     * Used only to map "magic" method names to sync operations. Providers
     * aren't required to service any of them.
     *
     * @var array<string,array{0:SyncOperation::*,1:class-string<SyncEntityInterface>}>
     */
    public $SyncOperationMagicMethods = [];

    /**
     * Signature => closure
     *
     * @var array<string,Closure>
     */
    public $CreateFromSignatureSyncClosures = [];

    /**
     * Signature => (int) $strict => closure
     *
     * @var array<string,array<int,Closure>>
     */
    public $CreateSyncEntityFromSignatureClosures = [];

    /**
     * (int) $strict => closure
     *
     * @var array<int,Closure>
     */
    public $CreateSyncEntityFromClosures = [];

    /**
     * Entity => sync operation => closure
     *
     * @var array<class-string<SyncEntityInterface>,array<SyncOperation::*,Closure|null>>
     */
    public $DeclaredSyncOperationClosures = [];

    /**
     * Lowercase "magic" sync operation method => closure
     *
     * @var array<string,Closure|null>
     */
    public $MagicSyncOperationClosures = [];

    /**
     * @param class-string<TClass> $class
     */
    public function __construct(string $class)
    {
        parent::__construct($class);

        $class = $this->Reflector;
        $this->IsSyncEntity = $class->implementsInterface(SyncEntityInterface::class);
        $this->IsSyncProvider = $class->implementsInterface(SyncProviderInterface::class);

        if ($this->IsSyncEntity) {
            $this->EntityNoun = Get::basename($this->Class);
            $plural = $class->getMethod('getPlural')->invoke(null);
            if ($plural !== null && $plural !== '' && strcasecmp($this->EntityNoun, $plural)) {
                $this->EntityPlural = $plural;
            }
        }

        if (!$this->IsSyncProvider) {
            return;
        }

        $namespace = (new ReflectionClass(AbstractSyncProvider::class))->getNamespaceName();
        foreach ($class->getInterfaces() as $name => $interface) {
            if (!$interface->isSubclassOf(SyncProviderInterface::class)) {
                continue;
            }

            // Add SyncProviderInterface interfaces to SyncProviderInterfaces
            $this->SyncProviderInterfaces[] = $name;

            // Add the entities they service to SyncProviderEntities
            /** @disregard P1006 */
            foreach (SyncIntrospector::providerToEntity($name) as $entity) {
                if (!is_a($entity, SyncEntityInterface::class, true)) {
                    continue;
                }
                $entity = SyncIntrospector::get($entity);
                $this->SyncProviderEntities[] = $entity->Class;

                // Map unambiguous kebab-case entity basenames to qualified names in
                // SyncProviderEntityBasenames
                $basename = Str::toKebabCase(Get::basename($entity->Class));
                $this->SyncProviderEntityBasenames[$basename] =
                    array_key_exists($basename, $this->SyncProviderEntityBasenames)
                        ? null
                        : $entity->Class;

                $fn = function ($operation, string $method) use ($entity, $class, $namespace) {
                    // If $method has already been processed, the entity it services
                    // is ambiguous and it can't be used
                    if (array_key_exists($method, $this->SyncOperationMethods)
                            || array_key_exists($method, $this->SyncOperationMagicMethods)) {
                        $this->SyncOperationMagicMethods[$method] = $this->SyncOperationMethods[$method] = null;

                        return;
                    }
                    if ($class->hasMethod($method)
                            && ($_method = $class->getMethod($method))->isPublic()) {
                        if ($_method->isStatic()
                                || !strcasecmp(Reflect::getPrototypeClass($_method)->getNamespaceName(), $namespace)) {
                            $this->SyncOperationMethods[$method] = null;

                            return;
                        }
                        $this->SyncOperationMethods[$method] = $_method->getName();

                        return;
                    }

                    /** @var SyncOperation::* $operation */
                    $this->SyncOperationMagicMethods[$method] = [$operation, $entity->Class];
                };

                $noun = Str::lower($entity->EntityNoun);

                if ($entity->EntityPlural !== null) {
                    $plural = Str::lower($entity->EntityPlural);
                    $fn(SyncOperation::CREATE_LIST, 'create' . $plural);
                    $fn(SyncOperation::READ_LIST, 'get' . $plural);
                    $fn(SyncOperation::UPDATE_LIST, 'update' . $plural);
                    $fn(SyncOperation::DELETE_LIST, 'delete' . $plural);
                }
                $fn(SyncOperation::CREATE, 'create' . $noun);
                $fn(SyncOperation::CREATE, 'create_' . $noun);
                $fn(SyncOperation::READ, 'get' . $noun);
                $fn(SyncOperation::READ, 'get_' . $noun);
                $fn(SyncOperation::UPDATE, 'update' . $noun);
                $fn(SyncOperation::UPDATE, 'update_' . $noun);
                $fn(SyncOperation::DELETE, 'delete' . $noun);
                $fn(SyncOperation::DELETE, 'delete_' . $noun);
                $fn(SyncOperation::CREATE_LIST, 'createlist_' . $noun);
                $fn(SyncOperation::READ_LIST, 'getlist_' . $noun);
                $fn(SyncOperation::UPDATE_LIST, 'updatelist_' . $noun);
                $fn(SyncOperation::DELETE_LIST, 'deletelist_' . $noun);
            }
        }
        $this->SyncProviderEntityBasenames = array_filter($this->SyncProviderEntityBasenames);
        $this->SyncOperationMethods = array_filter($this->SyncOperationMethods);
        $this->SyncOperationMagicMethods = array_filter($this->SyncOperationMagicMethods);
    }
}
