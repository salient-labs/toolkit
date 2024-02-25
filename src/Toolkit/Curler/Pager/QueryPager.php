<?php declare(strict_types=1);

namespace Lkrms\Curler\Pager;

use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Support\CurlerPageBuilder;
use Lkrms\Curler\Curler;
use Salient\Core\Utility\Arr;
use Closure;

/**
 * Increments a value in the query string and repeats the request until no
 * results are returned
 */
final class QueryPager implements ICurlerPager
{
    private ?string $PageKey;

    private Closure $Selector;

    private ?int $PageSize;

    /**
     * @var mixed[]
     */
    private array $Query;

    private ?string $QueryPageKey;

    /**
     * @var true|null
     */
    private $QueryPageKeyChecked;

    /**
     * @param string|null $pageKey The value to increment with each request, or
     * `null` to use the first value in the query. Added to the query string of
     * the second and subsequent requests if missing from the first.
     * @param Closure|string|null $selector Entities are collected from:
     * - `<Selector>($response)` if `$selector` is a closure,
     * - `$response[<Selector>]` if `$selector` is a string, or
     * - The response itself
     * @param int|null $pageSize Another page is requested if:
     * - `$pageSize` is `null` and at least one result is returned, or
     * - `$pageSize` is `>0` and exactly `$pageSize` results are returned
     */
    public function __construct(?string $pageKey = null, $selector = null, ?int $pageSize = null)
    {
        $this->PageKey = $pageKey;
        $this->Selector =
            $selector instanceof Closure
                ? $selector
                : (is_string($selector)
                    ? fn($response) => $response[$selector]
                    : fn($response) => Arr::listWrap($response));
        $this->PageSize = $pageSize;
    }

    public function prepareQuery(?array $query): ?array
    {
        // Save the query for subsequent requests
        $this->Query = $query ?: [];

        // Clear the last detected page key to ensure `getPage()` starts over
        if ($this->PageKey === null) {
            $this->QueryPageKey = null;
            $this->QueryPageKeyChecked = null;

            return $query;
        }

        // Or, if a page key has been set but doesn't appear in the query, add
        // an initial value to `$this->Query` without changing the first request
        if (!array_key_exists($this->PageKey, $this->Query)) {
            $this->Query[$this->PageKey] = 1;
        }

        return $query;
    }

    public function prepareData($data)
    {
        return $data;
    }

    public function prepareCurler(Curler $curler): Curler
    {
        return $curler;
    }

    public function getPage($data, Curler $curler, ?ICurlerPage $previous = null): ICurlerPage
    {
        $data = ($this->Selector)($data);

        if ($data &&
            (!$this->PageSize ||
                $this->PageSize < 1 ||
                count($data) === $this->PageSize)) {
            $key = $this->PageKey;
            if ($key === null) {
                if ($this->QueryPageKeyChecked) {
                    $key = $this->QueryPageKey;
                } else {
                    if ($this->Query && is_int(reset($this->Query))) {
                        $key = $this->QueryPageKey = key($this->Query);
                    }
                    $this->QueryPageKeyChecked = true;
                }
            }
            if ($key !== null) {
                $this->Query[$key]++;
                $nextUrl = $curler->getQueryUrl($this->Query);
            }
        }

        return CurlerPageBuilder::build()
            ->entities($data)
            ->curler($curler)
            ->previous($previous)
            ->nextUrl($nextUrl ?? null)
            ->go();
    }
}
