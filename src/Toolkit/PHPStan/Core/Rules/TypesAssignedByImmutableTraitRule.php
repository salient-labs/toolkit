<?php declare(strict_types=1);

namespace Salient\PHPStan\Core\Rules;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\VerbosityLevel;
use Salient\Core\Concern\ImmutableTrait;
use Salient\PHPStan\RuleTrait;

/**
 * @implements Rule<MethodCall>
 */
class TypesAssignedByImmutableTraitRule implements Rule
{
    use RuleTrait;

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            ($call = $this->getTraitMethodCall($node, $scope, ImmutableTrait::class))
            && ($call->MethodName === 'with' || $call->MethodName === 'without')
            && ($args = $node->getArgs())
            && ($propertyName = $scope->getType($args[0]->value))->isConstantScalarValue()->yes()
            && $propertyName->isString()->yes()
        ) {
            /** @var string */
            $propertyName = $propertyName->getConstantScalarValues()[0];
            $calledOn = $call->CalledOn;
            $has = $calledOn->hasProperty($propertyName);
            if (!$has->yes()) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Access to an undefined property %s::$%s.',
                        $calledOn->describe(VerbosityLevel::typeOnly()),
                        $propertyName,
                    ))
                        ->identifier('salient.property.notFound')
                        ->build(),
                ];
            }
            $property = $calledOn->getProperty($propertyName, $scope);
            if (
                $property->isPrivate()
                && $calledOn->getObjectClassNames() !== [$call->MethodClass->getName()]
            ) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Access to an inaccessible property %s::$%s.',
                        $calledOn->describe(VerbosityLevel::typeOnly()),
                        $propertyName,
                    ))
                        ->identifier('salient.property.private')
                        ->tip(sprintf(
                            'Insert %s or change the visibility of the property.',
                            ImmutableTrait::class,
                        ))
                        ->build(),
                ];
            }
            if ($call->MethodName === 'with' && count($args) > 1) {
                $propertyType = $property->getWritableType();
                if ($propertyType instanceof NeverType) {
                    $propertyType = $property->getReadableType();
                }
                $valueType = $scope->getType($args[1]->value);
                $accepts = $propertyType->isSuperTypeOf($valueType);
                if (!$accepts->yes()) {
                    return [
                        RuleErrorBuilder::message(sprintf(
                            'Property %s::$%s (%s) does not accept %s.',
                            $calledOn->describe(VerbosityLevel::typeOnly()),
                            $propertyName,
                            $propertyType->describe(VerbosityLevel::precise()),
                            $valueType->describe(VerbosityLevel::precise()),
                        ))
                            ->identifier('salient.property.type')
                            ->build(),
                    ];
                }
            }
        }
        return [];
    }
}
