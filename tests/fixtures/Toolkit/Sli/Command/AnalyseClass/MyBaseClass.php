<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

use Stringable;

/**
 * MyBaseClass
 *
 * @internal
 */
abstract class MyBaseClass implements MyInterface, Stringable
{
    /**
     * @inheritDoc
     */
    final public static function MyStaticMethod(MyInterface $instance): void
    {
        $instance->MyMethod();
    }

    protected function MyOverriddenMethod(): int
    {
        return 1;
    }
}
