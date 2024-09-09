<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Provider;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Curler\CurlerPage;
use Salient\Curler\CurlerPageRequest;
use Salient\Utility\Get;
use Salient\Utility\Str;

class MockoonPager implements CurlerPagerInterface
{
    public function getFirstRequest(
        RequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    ) {
        $query['limit'] ??= 50;
        unset($query['page']);
        return new CurlerPageRequest(
            $request->withUri($curler->replaceQuery($request->getUri(), $query)),
            $query,
        );
    }

    public function getPage(
        $data,
        RequestInterface $request,
        HttpResponseInterface $response,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null,
        ?array $query = null
    ): CurlerPageInterface {
        /** @var array<mixed[]> $data */
        $count = count($data);
        if ($previousPage) {
            /** @var CurlerPage $previousPage */
            $count += $previousPage->getCurrent();
            $total = $previousPage->getTotal();
        } else {
            $total = Get::integer(Str::coalesce($response->getHeaderLine('X-Filtered-Count'), null));
        }

        if ($data && $total !== null && $count < $total) {
            /** @var array{page?:int} $query */
            $query['page'] ??= 1;
            $query['page']++;
            $nextRequest = $request->withUri($curler->replaceQuery($request->getUri(), $query));
        }

        return new CurlerPage($data, $nextRequest ?? null, $query, $count, $total);
    }
}
