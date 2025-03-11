<?php declare(strict_types=1);

namespace Salient\PHPStan\Container;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use Salient\Contract\Container\ContainerInterface;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
class ContainerRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            $node->name instanceof Identifier
            && $node->name->toLowerString() === 'getas'
            && (new ObjectType(ContainerInterface::class))->isSuperTypeOf($scope->getType($node->var))->yes()
            && count($args = $node->getArgs()) > 1
            && ($id = $scope->getType($args[0]->value))->isClassString()->yes()
            && ($service = $scope->getType($args[1]->value))->isClassString()->yes()
        ) {
            $idType = $id->getClassStringObjectType();
            $serviceType = $service->getClassStringObjectType();
            if (!$serviceType->isSuperTypeOf($idType)->yes()) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        '%s is not subtype of %s.',
                        $idType->describe(VerbosityLevel::precise()),
                        $serviceType->describe(VerbosityLevel::precise()),
                    ))
                        ->identifier('salient.service.type')
                        ->build(),
                ];
            }
        }
        return [];
    }
}
