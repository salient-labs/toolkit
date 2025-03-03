<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Core;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use Salient\Core\Concern\ImmutableTrait;
use Salient\PHPStan\Core\ImmutableTraitRule;
use Salient\Tests\PHPStan\RuleTestCase;

/**
 * @covers \Salient\PHPStan\Core\ImmutableTraitRule
 * @covers \Salient\PHPStan\Internal\TraitMethodCall
 *
 * @extends RuleTestCase<ImmutableTraitRule>
 */
class ImmutableTraitRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ImmutableTraitRule(
            self::getContainer()->getByType(ReflectionProvider::class),
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testRule(): void
    {
        $doesNotAccept = 'Property %s::$%s (%s) does not accept %s.';
        $undefined = 'Access to an undefined property %s::$%s.';
        $private = 'Access to an inaccessible property %s::$%s.';
        $privateTip = sprintf('Insert %s or change the visibility of the property.', ImmutableTrait::class);
        $tips = [
            118 => $privateTip,
            126 => $privateTip,
        ];
        foreach ([
            [26, [$doesNotAccept, sprintf('static(%s)', MyClassWithImmutable::class), 'Bar', 'bool', '0']],
            [26, [$doesNotAccept, sprintf('$this(%s)', MyClassWithImmutable::class), 'Foo', '(int|string)', 'mixed']],
            [52, [$undefined, sprintf('static(%s)', MyClassWithImmutable::class), 'bar']],
            [52, [$undefined, sprintf('$this(%s)', MyClassWithImmutable::class), 'qux']],
            [72, [$doesNotAccept, sprintf('$this(%s)', MyClassWithImmutableAlias::class), 'Foo', 'int', 'string']],
            [118, [$private, sprintf('$this(%s)', MyClassWithInheritedImmutable::class), 'Foo']],
            [126, [$private, sprintf('$this(%s)', MyClassWithInheritedImmutable::class), 'Foo']],
            [177, [$doesNotAccept, sprintf('$this(%s)', MyClassWithReusedImmutable::class), 'Bar', 'int', 'bool']],
            [185, [$undefined, sprintf('$this(%s)', MyClassWithReusedImmutable::class), 'Qux']],
            // [225, [$doesNotAccept, sprintf('$this(%s)', MyClassWithMyImmutable::class), 'Bar', 'int', 'bool']],
            // [233, [$undefined, sprintf('$this(%s)', MyClassWithMyImmutable::class), 'Qux']],
        ] as [$line, $replacement]) {
            $error = [
                sprintf(...$replacement),
                $line,
            ];
            if (isset($tips[$line])) {
                $error[] = $tips[$line];
            }
            $expectedErrors[] = $error;
        }
        $this->analyse([__DIR__ . '/ImmutableTraitRuleFailures.php'], $expectedErrors);
    }
}
