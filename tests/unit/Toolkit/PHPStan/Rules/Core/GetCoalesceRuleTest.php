<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Rules\Core;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
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
        return new GetCoalesceRule();
    }

    public function testRule(): void
    {
        foreach ([11, 12, 13, 15, 16, 17] as $line) {
            $expectedErrors[] = [
                'Unnecessary use of Get::coalesce()',
                $line,
                'Use variadic argument(s) or replace with ??',
            ];
        }
        $this->analyse([TestCase::getFixturesPath(static::class) . 'Failures.php'], $expectedErrors);
    }
}
