<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface HasJsonSchema
{
    public const DRAFT_04_SCHEMA_ID = 'http://json-schema.org/draft-04/schema#';

    /**
     * Get a JSON Schema for the object
     *
     * @return array<string,mixed>
     */
    public function getJsonSchema(): array;
}
