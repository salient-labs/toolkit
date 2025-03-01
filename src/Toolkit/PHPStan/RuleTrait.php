<?php declare(strict_types=1);

namespace Salient\PHPStan;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Salient\PHPStan\Internal\TraitMethodCall;
use Salient\Utility\Reflect;
use ReflectionException;

/**
 * @internal
 */
trait RuleTrait
{
    private ReflectionProvider $ReflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->ReflectionProvider = $reflectionProvider;
    }

    /**
     * @param class-string|null $trait
     */
    private function getTraitMethodCall(MethodCall $node, Scope $scope, ?string $trait = null): ?TraitMethodCall
    {
        if ($node->name instanceof Identifier) {
            $name = $node->name->toString();
            $calledOn = $scope->getType($node->var);
            if (
                ($method = $scope->getMethodReflection($calledOn, $name))
                && ($methodClass = $method->getDeclaringClass())
                    ->hasNativeMethod($name = $method->getName())
            ) {
                $_methodClass = $methodClass->getNativeReflection();
                $_method = $_methodClass->getMethod($name);
                try {
                    $inClass = Reflect::isMethodInClass($_method, $_methodClass, $name);
                } catch (ReflectionException $ex) {
                    return null;
                }
                if (
                    $inClass === false
                    && ($_method = Reflect::getTraitMethod($_methodClass, $name, true))
                ) {
                    $traitName = $_method->getDeclaringClass()->getName();
                    if ($trait === null || $trait === $traitName) {
                        $methodTrait = $this->ReflectionProvider->getClass($traitName);
                        $methodName = $_method->getName();
                        return new TraitMethodCall(
                            $calledOn,
                            $method,
                            $methodClass,
                            $methodTrait,
                            $methodName,
                        );
                    }
                }
            }
        }
        return null;
    }
}
