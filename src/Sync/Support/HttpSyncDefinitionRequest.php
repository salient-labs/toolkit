<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;

/**
 * @property-read string $Path
 * @property-read array|null $Query
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

    public function __construct(string $path, ?array $query = null)
    {
        $this->Path  = $path;
        $this->Query = $query;
    }

}
