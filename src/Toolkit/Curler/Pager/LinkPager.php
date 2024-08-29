<?php declare(strict_types=1);

namespace Salient\Curler\Pager;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpHeader;
use Salient\Curler\CurlerPage;
use Salient\Http\Http;
use Salient\Http\Uri;
use Closure;

/**
 * Follows "Link" headers in responses from the endpoint
 */
final class LinkPager implements CurlerPagerInterface
{
    use HasEntitySelector;

    private ?int $PageSize;
    private string $PageSizeKey;

    /**
     * Creates a new LinkPager object
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
    ): RequestInterface {
        if ($this->PageSize === null) {
            return $request;
        }

        $query[$this->PageSizeKey] = $this->PageSize;

        return $request->withUri($curler->replaceQuery($request->getUri(), $query));
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

        $response = $curler->getLastResponse();
        if ($response && ($links = $response->getHeaderValues(HttpHeader::LINK))) {
            foreach ($links as $link) {
                /** @var array{string,rel?:string} */
                $link = Http::getParameters($link);
                if (($link['rel'] ?? null) === 'next') {
                    $link = trim($link[0], '<>');
                    $nextRequest = $request->withUri(
                        Uri::from($request->getUri())->follow($link)
                    );
                    break;
                }
            }
        }

        return new CurlerPage($data, $nextRequest ?? null);
    }
}
