<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Core\Rules;

use PHPStan\Rules\Rule;
use Salient\Container\Container;
use Salient\Core\Concern\HasMutator;
use Salient\PHPStan\Core\Rules\TypesAssignedByHasMutatorRule;
use Salient\Tests\PHPStan\RuleTestCase;

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

    /**
     * @runInSeparateProcess
     */
    public function testRule(): void
    {
        $doesNotAccept = 'Property %s::$%s (%s) does not accept %s.';
        $undefined = 'Access to an undefined property %s::$%s.';
        $private = 'Access to an inaccessible property %s::$%s.';
        $privateTip = sprintf('Insert %s or change the visibility of the property.', HasMutator::class);
        $tips = [
            112 => $privateTip,
            120 => $privateTip,
        ];
        foreach ([
            26 => [$doesNotAccept, sprintf('$this(%s)', MyClassWithMutator::class), 'Foo', '(int|string)', 'mixed'],
            27 => [$doesNotAccept, sprintf('static(%s)', MyClassWithMutator::class), 'Bar', 'bool', '0'],
            51 => [$undefined, sprintf('$this(%s)', MyClassWithMutator::class), 'qux'],
            52 => [$undefined, sprintf('static(%s)', MyClassWithMutator::class), 'bar'],
            70 => [$doesNotAccept, sprintf('$this(%s)', MyClassWithMutatorAlias::class), 'Foo', 'int', 'string'],
            112 => [$private, sprintf('$this(%s)', MyClassWithInheritedMutator::class), 'Foo'],
            120 => [$private, sprintf('$this(%s)', MyClassWithInheritedMutator::class), 'Foo'],
        ] as $line => $replacement) {
            $error = [
                sprintf(...$replacement),
                $line,
            ];
            if (isset($tips[$line])) {
                $error[] = $tips[$line];
            }
            $expectedErrors[] = $error;
        }
        $this->analyse([__DIR__ . '/TypesAssignedByHasMutatorRuleFailures.php'], $expectedErrors);
    }
}
