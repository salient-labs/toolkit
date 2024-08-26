<?php declare(strict_types=1);

namespace Salient\PHPStan\Type\Core;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use Salient\Utility\Arr;

class ArrFlattenReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
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
        return $methodReflection->getName() === 'flatten';
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

        $limit = -1;
        if (isset($args[1])) {
            $type = $scope->getType($args[1]->value);
            if (
                !$type->isConstantScalarValue()->yes()
                || !$type->isInteger()->yes()
            ) {
                return null;
            }
            $values = $type->getConstantScalarValues();
            if (count($values) !== 1) {
                return null;
            }
            /** @var int */
            $limit = $values[0];
        }

        $type = $scope->getType($args[0]->value);

        if ($type->isIterable()->no()) {
            return null;
        }

        $types = $this->getIterableValueTypes($type, $constant);

        do {
            $flattened = [];
            $fromIterable = false;
            foreach ($types as $type) {
                if (!$type->isIterable()->yes() || !$limit) {
                    $flattened[] = $type;
                    continue;
                }
                $fromIterable = true;
                $types = $this->getIterableValueTypes($type, $constant);
                foreach ($types as $type) {
                    $flattened[] = $type;
                }
            }
            $types = $flattened;
            $limit--;
        } while ($fromIterable && $limit);

        if ($constant) {
            $keys = [];
            foreach (array_keys($flattened) as $key) {
                $keys[] = new ConstantIntegerType($key);
            }
            return new ConstantArrayType($keys, $flattened);
        }

        return new ArrayType(
            new MixedType(),
            TypeCombinator::union(...$flattened),
        );
    }

    /**
     * @param-out bool $constant
     * @return Type[]
     */
    private function getIterableValueTypes(Type $type, ?bool &$constant = null): array
    {
        if (
            $type->isConstantArray()->yes()
            && count($arrays = $type->getConstantArrays()) === 1
        ) {
            $constant ??= true;
            return $arrays[0]->getValueTypes();
        }

        $constant = false;
        $type = $type->getIterableValueType();
        return $type instanceof UnionType
            ? $type->getTypes()
            : [$type];
    }
}
