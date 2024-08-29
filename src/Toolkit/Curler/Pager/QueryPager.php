<?php declare(strict_types=1);

namespace Salient\Curler\Pager;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Curler\CurlerPage;
use Salient\Utility\Arr;
use Closure;

/**
 * Increments a value in the query string of each request until no results are
 * returned
 */
final class QueryPager implements CurlerPagerInterface
{
    use HasEntitySelector;

    /** @var array-key|null */
    private $PageKey;
    private ?int $PageSize;
    /** @var mixed[] */
    private array $CurrentQuery;
    /** @var array-key|null */
    private $CurrentPageKey;

    /**
     * Creates a new QueryPager object
     *
     * @param array-key|null $pageKey The value to increment in the query string
     * of each request, or `null` to use the first value in the query. Added to
     * the second and subsequent requests if missing from the first.
     * @param (Closure(mixed): list<mixed>)|array-key|null $entitySelector Entities
     * are returned from:
     * - `$entitySelector($data)` if `$entitySelector` is a closure
     * - `Arr::get($data, $entitySelector)` if `$entitySelector` is a string or
     *   integer, or
     * - `$data` if `$entitySelector` is `null`
     * @param int|null $pageSize Another page is requested if:
     * - `$pageSize` is `null` and at least one result is returned, or
     * - `$pageSize` is greater than `0` and exactly `$pageSize` results are
     *   returned
     */
    public function __construct(
        $pageKey = null,
        $entitySelector = null,
        ?int $pageSize = null
    ) {
        $this->PageKey = $pageKey;
        $this->PageSize = $pageSize;
        $this->applyEntitySelector($entitySelector);
    }

    /**
     * @inheritDoc
     */
    public function getFirstRequest(
        RequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    ): RequestInterface {
        $this->CurrentQuery = $query ?? [];
        unset($this->CurrentPageKey);

        // If `$this->PageKey` does not appear in the query, add it to
        // `$this->CurrentQuery` without changing the first request
        if (
            $this->PageKey !== null
            && !array_key_exists($this->PageKey, $this->CurrentQuery)
        ) {
            $this->CurrentQuery[$this->PageKey] = 1;
        }

        return $request;
    }

    /**
     * @inheritDoc
     */
    public function getPage(
        $data,
        RequestInterface $request,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null
    ): CurlerPageInterface {
        $data = ($this->EntitySelector)($data);

        if ($data && (
            $this->PageSize === null
            || $this->PageSize < 1
            || count($data) === $this->PageSize
        )) {
            if ($this->PageKey === null && !$previousPage) {
                $this->CurrentPageKey =
                    $this->CurrentQuery && is_int(reset($this->CurrentQuery))
                        ? key($this->CurrentQuery)
                        : null;
            }
            $key = $this->PageKey ?? $this->CurrentPageKey;
            if ($key !== null) {
                $this->CurrentQuery[$key]++;
                $nextRequest = $request->withUri($curler->replaceQuery($request->getUri(), $this->CurrentQuery));
            }
        }

        return new CurlerPage($data, $nextRequest ?? null);
    }
}
