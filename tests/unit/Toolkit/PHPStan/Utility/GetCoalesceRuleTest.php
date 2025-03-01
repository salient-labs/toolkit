<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Utility;

use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Rules\Rule;
use Salient\PHPStan\Utility\GetCoalesceRule;
use Salient\Tests\PHPStan\RuleTestCase;

/**
 * @covers \Salient\PHPStan\Utility\GetCoalesceRule
 *
 * @extends RuleTestCase<GetCoalesceRule>
 */
class GetCoalesceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new GetCoalesceRule(
            self::getContainer()->getByType(ExprPrinter::class),
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testRule(): void
    {
        foreach ([
            11 => 'null',
            12 => 'null',
            13 => 'null ?? null',
            15 => '$a ?? null',
            16 => '$b ?? $a ?? null',
            17 => '$c ?? null',
        ] as $line => $replacement) {
            $expectedErrors[] = [
                'Unnecessary use of Get::coalesce().',
                $line,
                'Use a variadic argument or replace with: ' . $replacement,
            ];
        }
        $this->analyse([__DIR__ . '/GetCoalesceRuleFailures.php'], $expectedErrors);
    }
}
