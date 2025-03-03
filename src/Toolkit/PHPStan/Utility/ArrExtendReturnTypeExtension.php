<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Salient\PHPStan\Internal\ReturnTypeExtensionTrait;
use Salient\Utility\Arr;

class ArrExtendReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    use ReturnTypeExtensionTrait;

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
        if ($args = $this->getArgTypes($methodCall, $scope)) {
            $array = array_shift($args);
            if (!$array->IsOptional && $array->isArray()->yes()) {
                if (!$args) {
                    return $array->Type;
                }
                if (
                    $array->isConstantArray()->yes()
                    && count($constantArrays = $array->getConstantArrays()) === 1
                ) {
                    $constant = true;
                    foreach ($args as $arg) {
                        if (
                            !$arg->IsFromUnpackedConstantArray
                            && !$arg->isConstantValue()->yes()
                        ) {
                            $constant = false;
                            break;
                        }
                    }
                    if ($constant) {
                        $builder = ConstantArrayTypeBuilder::createFromConstantArray($constantArrays[0]);
                        $arrayValueType = $array->getIterableValueType();
                        foreach ($args as $arg) {
                            if (!$arrayValueType->isSuperTypeOf($arg->Type)->yes()) {
                                $builder->setOffsetValueType(null, $arg->Type, $arg->IsOptional);
                            }
                        }
                        return $builder->getArray();
                    }
                }
                $argsAreOptional = true;
                foreach ($args as $arg) {
                    $argsAreOptional = $argsAreOptional && $arg->IsOptional;
                    $types[] = $arg->Type;
                }
                $type = new ArrayType(
                    TypeCombinator::union($array->getIterableKeyType(), new IntegerType()),
                    TypeCombinator::union($array->getIterableValueType(), ...$types),
                );
                $accessoryTypes = [];
                if (!$argsAreOptional) {
                    $accessoryTypes[] = new NonEmptyArrayType();
                }
                if ($array->isList()->yes()) {
                    $accessoryTypes[] = new AccessoryArrayListType();
                }
                return $accessoryTypes
                    ? TypeCombinator::intersect($type, ...$accessoryTypes)
                    : $type;
            }
        }
        return new NeverType();
    }
}
