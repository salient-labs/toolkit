<?php

declare(strict_types=1);

namespace Lkrms\Curler\Pager;

use Closure;
use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Curler;
use Lkrms\Curler\Support\CurlerPageBuilder;
use Lkrms\Facade\Convert;
use UnexpectedValueException;

/**
 * Increments a value in the query string and repeats the request until no
 * results are returned
 *
 */
final class QueryPager implements ICurlerPager
{
    /**
     * @var string|null
     */
    private $PageKey;

    /**
     * @var Closure
     */
    private $Selector;

    /**
     * @var array|null
     */
    private $Query;

    /**
     * @param string|null $pageKey The value to increment with each request, or
     * `null` to use the first value in the query.
     * @param Closure|string|null $selector Entities are collected from:
     * - `<Selector>($response)` if `$selector` is a closure,
     * - `$response[<Selector>]` if `$selector` is a string, or
     * - The response itself
     */
    public function __construct(?string $pageKey = null, $selector = null)
    {
        $this->PageKey  = $pageKey;
        $this->Selector = ($selector instanceof Closure
            ? $selector
            : (is_string($selector)
                ? fn($response) => $response[$selector]
                : fn($response) => Convert::toList($response)));
    }

    public function prepareQuery(?array $query): ?string
    {
        if (is_null($this->PageKey))
        {
            if (is_null($query) ||
                !is_int(reset($query)) ||
                !($pageKey = key($query)))
            {
                throw new UnexpectedValueException("First element of query array must be an integer");
            }
            $this->PageKey = $pageKey;
        }
        $this->Query = $query;

        return null;
    }

    public function prepareData(?array $data): ?array
    {
        return $data;
    }

    public function prepareCurler(Curler $curler): void
    {
    }

    public function getPage($data, Curler $curler, ?ICurlerPage $previous = null): ICurlerPage
    {
        $data = ($this->Selector)($data);

        if ($data)
        {
            $this->Query[$this->PageKey]++;
            $nextUrl = $curler->getQueryUrl($this->Query);
        }

        return CurlerPageBuilder::build()
            ->entities($data)
            ->curler($curler)
            ->previous($previous)
            ->nextUrl($nextUrl ?? null)
            ->go();
    }

}
