<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

/**
 * @api
 */
interface CurlerPageInterface extends CurlerPageRequestInterface
{
    /**
     * Get a list of entities returned by the endpoint
     *
     * @return list<mixed>
     */
    public function getEntities(): array;
}
