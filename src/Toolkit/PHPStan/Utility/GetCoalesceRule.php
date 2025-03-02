<?php declare(strict_types=1);

namespace Salient\PHPStan\Utility;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Printer\ExprPrinter;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Salient\Utility\Get;

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

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (
            $node->class instanceof Name
            && $node->name instanceof Identifier
            && $node->class->toString() === Get::class
            && $node->name->toLowerString() === 'coalesce'
        ) {
            $values = [];
            foreach ($node->getArgs() as $arg) {
                // Don't report an error if there is a variadic argument
                if ($arg->unpack) {
                    return [];
                }
                $values[] = $arg->value;
            }
            if ($values) {
                foreach ($values as $value) {
                    $expr[] = $this->Printer->printExpr($value);
                }
            } else {
                $expr[] = 'null';
            }
            return [
                RuleErrorBuilder::message('Unnecessary use of Get::coalesce().')
                    ->identifier('salient.needless.coalesce')
                    ->tip(sprintf(
                        'Use a variadic argument or replace with: %s',
                        implode(' ?? ', $expr),
                    ))
                    ->build(),
            ];
        }
        return [];
    }
}
