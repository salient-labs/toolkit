<?php declare(strict_types=1);

namespace Lkrms\Exception\Contract;

use Throwable;

interface ExceptionInterface extends Throwable
{
    /**
     * Get an array that maps names to formatted content
     *
     * @return array<string,string>
     */
    public function getDetail(): array;
}
