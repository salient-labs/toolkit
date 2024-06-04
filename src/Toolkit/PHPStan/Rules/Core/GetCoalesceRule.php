<?php declare(strict_types=1);

namespace Salient\PHPStan\Rules\Core;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Salient\Core\Utility\Get;

/**
 * @implements Rule<StaticCall>
 */
class GetCoalesceRule implements Rule
{
    private ExprPrinter $Printer;

    public function __construct(ExprPrinter $printer)
    {
        $this->Printer = $printer;
    }

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

        $expr = [];
        foreach ($node->getArgs() as $arg) {
            if ($arg->unpack) {
                return [];
            }
            $expr[] = $arg->value;
        }

        if ($expr) {
            $expr = array_map([$this->Printer, 'printExpr'], $expr);
        } else {
            $expr = ['null'];
        }

        return [
            RuleErrorBuilder::message('Unnecessary use of Get::coalesce().')
                ->identifier('salient.needless.coalesce')
                ->tip(sprintf('Use a variadic argument or replace with: %s', implode(' ?? ', $expr)))
                ->build(),
        ];
    }
}
