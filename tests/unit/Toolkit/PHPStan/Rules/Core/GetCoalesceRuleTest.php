<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Rules\Core;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Salient\Container\Container;
use Salient\PHPStan\Rules\Core\GetCoalesceRule;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\PHPStan\Rules\Core\GetCoalesceRule
 *
 * @extends RuleTestCase<GetCoalesceRule>
 */
class GetCoalesceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return (new Container())->get(GetCoalesceRule::class);
    }

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
        $this->analyse([TestCase::getFixturesPath(static::class) . 'Failures.php'], $expectedErrors);
    }
}
