<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Container;

use PHPStan\Rules\Rule;
use Salient\PHPStan\Container\ContainerRule;
use Salient\Tests\PHPStan\RuleTestCase;

/**
 * @covers \Salient\PHPStan\Container\ContainerRule
 *
 * @extends RuleTestCase<ContainerRule>
 */
class ContainerRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ContainerRule();
    }

    /**
     * @runInSeparateProcess
     */
    public function testRule(): void
    {
        $notSubtype = '%s is not subtype of %s.';
        foreach ([
            [17, [$notSubtype, C::class, A::class]],
            [18, [$notSubtype, A::class, B::class]],
        ] as [$line, $replacement]) {
            $expectedErrors[] = [
                sprintf(...$replacement),
                $line,
            ];
        }
        $this->analyse([__DIR__ . '/ContainerRuleFailures.php'], $expectedErrors);
    }
}
