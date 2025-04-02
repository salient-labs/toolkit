<?php declare(strict_types=1);

namespace Salient\Curler\Pager;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;
use Salient\Curler\CurlerPage;
use Salient\Curler\CurlerPageRequest;
use Salient\Http\HttpUtil;
use Salient\Http\Uri;
use Closure;

/**
 * Follows "Link" headers with rel="next" in responses from the endpoint
 *
 * @api
 */
final class LinkPager implements CurlerPagerInterface
{
    use HasEntitySelector;

    private ?int $PageSize;
    private string $PageSizeKey;

    /**
     * @api
     *
     * @param (Closure(mixed): list<mixed>)|array-key|null $entitySelector Entities
     * are returned from:
     * - `$entitySelector($data)` if `$entitySelector` is a closure
     * - `Arr::get($data, $entitySelector)` if `$entitySelector` is a string or
     *   integer, or
     * - `$data` if `$entitySelector` is `null`
     */
    public function __construct(
        ?int $pageSize = null,
        $entitySelector = null,
        string $pageSizeKey = 'per_page'
    ) {
        $this->PageSize = $pageSize;
        if ($pageSize !== null) {
            $this->PageSizeKey = $pageSizeKey;
        }
        $this->applyEntitySelector($entitySelector);
    }

    /**
     * @inheritDoc
     */
    public function getFirstRequest(
        RequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    ) {
        if ($this->PageSize === null) {
            return $request;
        }

        $query[$this->PageSizeKey] = $this->PageSize;
        return new CurlerPageRequest(
            $curler->replaceQuery($request, $query),
            $query,
        );
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

        foreach ($response->getHeaderValues(self::HEADER_LINK) as $link) {
            /** @var array{string,rel?:string} */
            $link = HttpUtil::getParameters($link);
            if (($link['rel'] ?? null) === 'next') {
                $link = trim($link[0], '<>');
                $uri = $request->getUri();
                $uri = Uri::from($uri)->follow($link);
                $nextRequest = $request->withUri($uri);
                break;
            }
        }

        return new CurlerPage($data, $nextRequest ?? null);
    }
}
