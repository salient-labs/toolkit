<?php declare(strict_types=1);

namespace Salient\PHPStan\Core\Rules;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use Salient\Core\Concern\HasMutator;

/**
 * @implements Rule<MethodCall>
 */
class TypesAssignedByHasMutatorRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var MethodCall $node */
        $methodName = $node->name;
        if (!$methodName instanceof Identifier) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $calledOnType = $scope->getType($node->var);
        $methodReflection = $scope->getMethodReflection($calledOnType, $methodName->toString());
        if (!$methodReflection) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $prototypeReflection = $methodReflection->getPrototype();
        // @phpstan-ignore phpstanApi.instanceofAssumption
        if (!$prototypeReflection instanceof PhpMethodReflection) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $traitReflection = $prototypeReflection->getDeclaringTrait();
        if (!$traitReflection || $traitReflection->getName() !== HasMutator::class) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $classReflection = $prototypeReflection->getDeclaringClass();
        $aliases = array_change_key_case($classReflection->getNativeReflection()->getTraitAliases());
        $name = $methodName->toLowerString();
        if (isset($aliases[$name])) {
            $name = explode('::', $aliases[$name])[1];
        }
        if ($name !== 'with' && $name !== 'without') {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $args = $node->getArgs();
        if (!$args) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        $propertyName = $scope->getType($args[0]->value);
        if (
            !$propertyName->isConstantScalarValue()->yes()
            || !$propertyName->isString()->yes()
        ) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        /** @var string */
        $propertyName = $propertyName->getConstantScalarValues()[0];
        $has = $calledOnType->hasProperty($propertyName);
        if (!$has->yes()) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Access to an undefined property %s::$%s.',
                    $calledOnType->describe(VerbosityLevel::typeOnly()),
                    $propertyName,
                ))
                    ->identifier('salient.property.notFound')
                    ->build(),
            ];
        }
        $propertyReflection = $calledOnType->getProperty($propertyName, $scope);
        if (
            $propertyReflection->isPrivate()
            && ($scopeClass = $scope->getClassReflection())
            && !(new ObjectType($scopeClass->getName()))->equals(
                new ObjectType($methodReflection->getDeclaringClass()->getName())
            )
        ) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Access to an inaccessible property %s::$%s.',
                    $calledOnType->describe(VerbosityLevel::typeOnly()),
                    $propertyName,
                ))
                    ->identifier('salient.property.private')
                    ->tip(sprintf(
                        'Insert %s or change the visibility of the property.',
                        HasMutator::class,
                    ))
                    ->build(),
            ];
        }
        if ($name !== 'with' || count($args) < 2) {
            return [];
        }
        $propertyType = $propertyReflection->getWritableType();
        if ($propertyType instanceof NeverType) {
            $propertyType = $propertyReflection->getReadableType();
        }
        $valueType = $scope->getType($args[1]->value);
        $accepts = $propertyType->isSuperTypeOf($valueType);
        if (!$accepts->yes()) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Property %s::$%s (%s) does not accept %s.',
                    $calledOnType->describe(VerbosityLevel::typeOnly()),
                    $propertyName,
                    $propertyType->describe(VerbosityLevel::precise()),
                    $valueType->describe(VerbosityLevel::precise()),
                ))
                    ->identifier('salient.property.type')
                    ->build(),
            ];
        }
        return [];
    }
}
