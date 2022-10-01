<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;

final class DeferredSyncEntity implements IReadable
{
    use TFullyReadable;

    /**
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * @var ISyncContext
     */
    protected $Context;

    /**
     * @var string
     */
    protected $Entity;

    /**
     * @var int|string|array<int|string>
     */
    protected $Deferred;

    private $Replace;

    /**
     * @param int|string|array<int|string> $deferred
     * @param mixed $replace Provide a reference to the variable, property or
     * array element to replace when the entity is resolved.
     */
    public function __construct(ISyncProvider $provider, ISyncContext $context, string $entity, $deferred, &$replace)
    {
        $this->Provider = $provider;
        $this->Context  = $context;
        $this->Entity   = $entity;
        $this->Deferred = $deferred;
        $this->Replace  = & $replace;
    }

}
