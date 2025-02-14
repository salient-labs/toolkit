<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Legacy\IntrospectionClass;
use Closure;

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
    }
}
