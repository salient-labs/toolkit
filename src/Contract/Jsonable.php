<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface Jsonable
{
    /**
     * Get a JSON representation of the object
     *
     * @param int $flags Passed to {@see json_encode()}.
     */
    public function toJson(int $flags = 0): string;
}
