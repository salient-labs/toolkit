<?php

declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use RuntimeException;

/**
 * Implements ICurlerPage
 *
 */
class CurlerPage implements ICurlerPage
{
    /**
     * @var array
     */
    private $Entities;

    /**
     * @var bool
     */
    private $IsLastPage;

    /**
     * @var string|null
     */
    private $NextUrl;

    /**
     * @var array|null
     */
    private $NextData;

    /**
     * @var CurlerHeaders|null
     */
    private $NextHeaders;

    public function __construct(array $entities, Curler $curler, ?string $nextUrl = null, ?bool $isLastPage = null, ?array $nextData = null, ?CurlerHeaders $nextHeaders = null)
    {
        $this->Entities   = $entities;
        $this->IsLastPage = is_null($isLastPage) ? empty($nextUrl) : $isLastPage;

        if (!$this->IsLastPage)
        {
            $this->NextUrl     = $nextUrl ?: $curler->BaseUrl . $curler->QueryString;
            $this->NextData    = $nextData;
            $this->NextHeaders = $nextHeaders;
        }
    }

    public function entities(): array
    {
        return $this->Entities;
    }

    public function isLastPage(): bool
    {
        return $this->IsLastPage;
    }

    private function assertHasNextPage()
    {
        if ($this->IsLastPage)
        {
            throw new RuntimeException("No more pages");
        }
    }

    public function nextUrl(): string
    {
        $this->assertHasNextPage();

        return $this->NextUrl;
    }

    public function nextData(): ?array
    {
        $this->assertHasNextPage();

        return $this->NextData;
    }

    public function nextHeaders(): ?CurlerHeaders
    {
        $this->assertHasNextPage();

        return $this->NextHeaders;
    }

}
