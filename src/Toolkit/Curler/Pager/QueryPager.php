<?php declare(strict_types=1);

namespace Salient\Curler\Pager;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Curler\CurlerPage;
use Salient\Utility\Test;
use Closure;
use LogicException;

/**
 * Increments a value in the query string of each request until no results are
 * returned
 *
 * @api
 */
final class QueryPager implements CurlerPagerInterface
{
    use HasEntitySelector;

    /** @var array-key|null */
    private $PageKey;
    /** @var int<1,max>|null */
    private ?int $PageSize;

    /**
     * @api
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
     * @param int<1,max>|null $pageSize Another page is requested if:
     * - `$pageSize` is `null` and at least one result is returned, or
     * - exactly `$pageSize` results are returned
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
        return $request;
    }

    /**
     * @inheritDoc
     */
    public function getPage(
        $data,
        RequestInterface $request,
        HttpResponseInterface $response,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null,
        ?array $query = null
    ): CurlerPageInterface {
        $data = ($this->EntitySelector)($data);

        if ($data && (
            $this->PageSize === null
            || count($data) === $this->PageSize
        )) {
            $key = $this->PageKey;
            if ($key === null && $query && Test::isInteger(reset($query))) {
                $key = key($query);
            }
            if ($key === null) {
                throw new LogicException('No page key and no integer value at query offset 0');
            }
            $query[$key] ??= 1;
            if (!Test::isInteger($query[$key])) {
                throw new LogicException('Value at page key is not an integer');
            }
            $query[$key]++;
            $nextRequest = $curler->replaceQuery($request, $query);
        }

        return new CurlerPage($data, $nextRequest ?? null, $query);
    }
}
