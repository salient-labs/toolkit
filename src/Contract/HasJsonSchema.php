<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface HasJsonSchema
{
    /**
     * Get the object's JSON Schema
     *
     * @return array<string,mixed>
     */
    public function getJsonSchema(): array;
}
