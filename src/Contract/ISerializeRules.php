<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Provides instructions for serializing nested entities
 *
 */
interface ISerializeRules
{
    public function getIncludeMeta(): bool;

    public function getSortByKey(): bool;

    public function getMaxDepth(): ?int;

    public function getDetectRecursion(): bool;

    public function getFlags(): int;

}
