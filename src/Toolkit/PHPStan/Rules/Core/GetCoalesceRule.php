<?php declare(strict_types=1);

namespace Salient\PHPStan\Rules\Core;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Salient\Core\Utility\Get;

/**
 * @implements Rule<StaticCall>
 */
class GetCoalesceRule implements Rule
{
    /**
     * @codeCoverageIgnore
     */
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var StaticCall $node */
        if (!(
            $node->class instanceof Name
            && $node->name instanceof Identifier
            && $node->class->toString() === Get::class
            && $node->name->toString() === 'coalesce'
        )) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }

        foreach ($node->getArgs() as $arg) {
            if ($arg->unpack) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message('Unnecessary use of Get::coalesce()')
                ->identifier('salient.needless.coalesce')
                ->tip('Use variadic argument(s) or replace with ??')
                ->build(),
        ];
    }
}
