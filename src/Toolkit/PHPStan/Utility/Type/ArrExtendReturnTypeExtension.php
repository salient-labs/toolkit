<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility\Type;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Salient\Utility\Arr;

class ArrExtendReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @codeCoverageIgnore
     */
    public function getClass(): string
    {
        return Arr::class;
    }

    public function isStaticMethodSupported(
        MethodReflection $methodReflection
    ): bool {
        return $methodReflection->getName() === 'extend';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        $args = $methodCall->getArgs();

        if ($args === []) {
            return null;
        }

        // Get the `$array` argument and its type
        $arrayArg = array_shift($args);
        $arrayType = $scope->getType($arrayArg->value);

        if ($arrayType->isArray()->no()) {
            return null;
        }

        // If there are no more arguments, do nothing
        if ($args === []) {
            return $arrayType;
        }

        // Otherwise, check if `$array` and every subsequent argument is a
        // constant
        $constant = $arrayType->isConstantArray()->yes()
            && count($arrayType->getConstantArrays()) === 1;

        $argTypes = [];
        foreach ($args as $arg) {
            $type = $scope->getType($arg->value);
            $argTypes[] = [$type, $arg->unpack];

            if (!$constant) {
                continue;
            }

            // Unpack variadic arguments
            if ($arg->unpack) {
                $constant = $type->isConstantArray()->yes()
                    && count($type->getConstantArrays()) === 1;
                continue;
            }

            $constant = $type->isConstantValue()->yes();
        }

        // If so, build a new constant array type by adding elements to `$array`
        if ($constant) {
            [$array] = $arrayType->getConstantArrays();
            $builder = ConstantArrayTypeBuilder::createFromConstantArray($array);
            $arrayValueType = $array->getIterableValueType();

            // Generalize the array if `$array` has any optional elements with
            // numeric keys
            $generalize = false;
            if ($optionalKeys = $array->getOptionalKeys()) {
                $keyTypes = $array->getKeyTypes();
                $int = new IntegerType();
                foreach ($optionalKeys as $key) {
                    if (!$int->isSuperTypeOf($keyTypes[$key])->no()) {
                        $generalize = true;
                        break;
                    }
                }
            }

            foreach ($argTypes as [$argType, $unpack]) {
                if ($unpack) {
                    [$array] = $argType->getConstantArrays();
                    foreach ($array->getValueTypes() as $i => $valueType) {
                        // Ignore values already present in `$array`
                        if ($arrayValueType->isSuperTypeOf($valueType)->yes()) {
                            continue;
                        }
                        // Generalize the array if any variadic arguments after
                        // `$array` have optional elements
                        $optional = false;
                        if ($array->isOptionalKey($i)) {
                            $optional = true;
                            $generalize = true;
                        }
                        $builder->setOffsetValueType(null, $valueType, $optional);
                    }
                    continue;
                }

                if ($arrayValueType->isSuperTypeOf($argType)->yes()) {
                    continue;
                }
                $builder->setOffsetValueType(null, $argType);
            }

            $built = $builder->getArray();
            return $generalize
                ? $built->generalize(GeneralizePrecision::lessSpecific())
                : $built;
        }

        // Otherwise, add `int` to the array's key type, and the types of
        // subsequent arguments to its value type
        $keyType = $arrayType->getIterableKeyType();
        $valueType = $arrayType->getIterableValueType();
        $nonEmpty = $arrayType->isIterableAtLeastOnce()->yes();
        $keyType = TypeCombinator::union($keyType, new IntegerType());
        foreach ($argTypes as [$argType, $unpack]) {
            if ($unpack) {
                $nonEmpty = $nonEmpty || $argType->isIterableAtLeastOnce()->yes();
                $argType = $argType->getIterableValueType();
            } else {
                $nonEmpty = true;
            }
            $valueType = TypeCombinator::union($valueType, $argType);
        }
        $type = new ArrayType($keyType, $valueType);
        if ($nonEmpty) {
            $type = TypeCombinator::intersect($type, new NonEmptyArrayType());
        }
        if ($arrayType->isList()->yes()) {
            $type = TypeCombinator::intersect($type, new AccessoryArrayListType());
        }
        return $type;
    }
}
