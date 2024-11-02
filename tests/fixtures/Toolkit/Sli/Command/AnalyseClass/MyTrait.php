<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

/**
 * Summary of MyTrait
 *
 * @property-write mixed[] $MyMagicTraitProperty
 *
 * @method string MyMagicTraitMethod()
 *
 * @internal
 */
trait MyTrait
{
    private int $MyIntProperty = 2;

    /**
     * Summary of MyTrait::MyMethod()
     *
     * @return mixed
     */
    public function MyMethod() {}

    /**
     * Summary of MyTrait::MyOverriddenMethod()
     */
    public function MyOverriddenMethod(): int
    {
        return 2;
    }
}
