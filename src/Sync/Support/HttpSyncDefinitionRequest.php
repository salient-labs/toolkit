<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;

/**
 * @property-read string $Path
 * @property-read array|null $Query
 * @property-read Closure|null $HeadersCallback
 * @property-read Closure|null $PagerCallback
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
     * @var Closure|null
     */
    protected $PagerCallback;

    /**
     * @param Closure|null $headersCallback Closure signature: `fn(Curler $curler, int $operation, ISyncContext $ctx, ...$args): ?CurlerHeaders`
     * @param Closure|null $pagerCallback Closure signature: `fn(Curler $curler, int $operation, ISyncContext $ctx, ...$args): ?ICurlerPager`
     */
    public function __construct(string $path, ?array $query = null, ?Closure $headersCallback = null, ?Closure $pagerCallback = null)
    {
        $this->Path            = $path;
        $this->Query           = $query;
        $this->HeadersCallback = $headersCallback;
        $this->PagerCallback   = $pagerCallback;
    }
}
