<?php declare(strict_types=1);

namespace Lkrms\Contract;

interface HasJsonSchema
{
    public const DRAFT_04_SCHEMA_ID = 'http://json-schema.org/draft-04/schema#';

    /**
     * Get the object's JSON Schema
     *
     * @return array<string,mixed>
     */
    public function getJsonSchema(): array;
}
