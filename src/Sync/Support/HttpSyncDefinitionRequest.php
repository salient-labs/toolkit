<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;

/**
 * @property-read string $Path
 * @property-read array|null $Query
 * @property-read Closure|null $HeadersCallback
 */
final class HttpSyncDefinitionRequest implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Path;

    /**
     * @var array|null
     */
    protected $Query;

    /**
     * @var Closure|null
     */
    protected $HeadersCallback;

    /**
     * @param Closure|null $headersCallback Closure signature: `fn(Curler $curler, int $operation, SyncContext $ctx, ...$args): ?CurlerHeaders`
     */
    public function __construct(string $path, ?array $query = null, ?Closure $headersCallback = null)
    {
        $this->Path  = $path;
        $this->Query = $query;
        $this->HeadersCallback = $headersCallback;
    }

}
