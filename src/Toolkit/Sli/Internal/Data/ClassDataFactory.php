<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

/**
 * @internal
 */
interface ClassDataFactory
{
    /**
     * @param class-string $class
     */
    public function getClassData(string $class): ClassData;
}
