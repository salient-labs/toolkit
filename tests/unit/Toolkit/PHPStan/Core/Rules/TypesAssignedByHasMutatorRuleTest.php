<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Core\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Salient\Container\Container;
use Salient\PHPStan\Core\Rules\TypesAssignedByHasMutatorRule;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\PHPStan\Core\Rules\TypesAssignedByHasMutatorRule
 *
 * @extends RuleTestCase<TypesAssignedByHasMutatorRule>
 */
class TypesAssignedByHasMutatorRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return (new Container())->get(TypesAssignedByHasMutatorRule::class);
    }

    public function testRule(): void
    {
        $doesNotAccept = 'Property %s::$%s (%s) does not accept %s.';
        $undefined = 'Access to an undefined property %s::$%s.';
        foreach ([
            26 => [$doesNotAccept, sprintf('$this(%s)', MyClassWithMutator::class), 'Foo', '(int|string)', 'mixed'],
            27 => [$doesNotAccept, sprintf('static(%s)', MyClassWithMutator::class), 'Bar', 'bool', '0'],
            51 => [$undefined, sprintf('$this(%s)', MyClassWithMutator::class), 'qux'],
            52 => [$undefined, sprintf('static(%s)', MyClassWithMutator::class), 'bar'],
            70 => [$doesNotAccept, sprintf('$this(%s)', MyClassWithMutatorAlias::class), 'Foo', 'int', 'string'],
        ] as $line => $replacement) {
            $expectedErrors[] = [
                sprintf(...$replacement),
                $line,
            ];
        }
        $this->analyse([TestCase::getFixturesPath(static::class) . 'Failures.php'], $expectedErrors);
    }
}
