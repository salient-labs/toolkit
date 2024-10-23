<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

/**
 * MyTrait
 *
 * @internal
 */
trait MyTrait
{
    private int $MyIntProperty = 2;

    /**
     * MyTrait::MyMethod()
     *
     * @return mixed
     */
    public function MyMethod() {}

    /**
     * MyTrait::MyOverriddenMethod()
     */
    public function MyOverriddenMethod(): int
    {
        return 2;
    }
}
