<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\Concept\Facade;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\PhpDoc\PhpDoc;
use Lkrms\Utility\Reflect;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Generates facades
 */
final class GenerateFacade extends GenerateCommand
{
    /**
     * Methods that shouldn't be surfaced by the Facade
     */
    private const SKIP_METHODS = [
        'getReadable',
        'getWritable',
        'withFacade',
        'withoutFacade',
        // These are displaced by Facade
        'isLoaded',
        'load',
        'swap',
        'unload',
        'unloadAll',
        'getInstance',
    ];

    private string $ClassFqcn = '';

    private string $FacadeFqcn = '';

    /**
     * @var string[]
     */
    private array $SkipMethods = [];

    public function description(): string
    {
        return 'Generate a static interface to a class';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('class')
                ->valueName('class')
                ->description('The class to generate a facade for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            CliOption::build()
                ->long('generate')
                ->valueName('facade_class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->FacadeFqcn),
            CliOption::build()
                ->long('skip')
                ->short('k')
                ->description('Exclude a method from the facade')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->SkipMethods),
            ...$this->getOutputOptionList('facade'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->reset();

        $this->SkipMethods = array_merge($this->SkipMethods, self::SKIP_METHODS);

        $classFqcn = $this->getRequiredFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $classClass
        );

        $this->getRequiredFqcnOptionValue(
            'facade',
            $this->FacadeFqcn,
            EnvVar::NS_FACADE,
            $facadeClass,
            $facadeNamespace
        );

        $this->assertClassIsInstantiable($classFqcn);

        $this->OutputClass = $facadeClass;
        $this->OutputNamespace = $facadeNamespace;

        $this->loadInputClass($classFqcn);

        $classPrefix = $this->getClassPrefix();

        $service = $this->getFqcnAlias($classFqcn, $classClass);
        $extends = $this->getFqcnAlias(Facade::class);

        $desc = $this->Description === null
            ? "A facade for $classPrefix$classFqcn"
            : $this->Description;

        $_methods = $this->InputClass->getMethods(ReflectionMethod::IS_PUBLIC);

        usort(
            $_methods,
            fn(ReflectionMethod $a, ReflectionMethod $b) =>
                $a->isConstructor()
                    ? -1
                    : ($b->isConstructor()
                        ? 1
                        : $a->getName() <=> $b->getName())
        );
        $facadeMethods = [
            " * @method static bool isLoaded() True if the facade's underlying instance is loaded",
            " * @method static void load($service|null \$instance = null) Load the facade's underlying instance",
            " * @method static void swap($service \$instance) Replace the facade's underlying instance",
            " * @method static void unload() Remove the facade's underlying instance if loaded",
            " * @method static $service getInstance() Get the facade's underlying instance, loading it if necessary",
        ];
        $methods = [];
        $toDeclare = [];
        foreach ($_methods as $_method) {
            $declaring = $_method->getDeclaringClass()->getName();
            $methodName = $_method->getName();
            $getMethodFqsen = fn() => $this->getTypeAlias($declaring) . "::{$methodName}()";
            $_params = $_method->getParameters();
            $docBlocks = Reflect::getAllMethodDocComments($_method, $classDocBlocks);
            $phpDoc = PhpDoc::fromDocBlocks($docBlocks, $classDocBlocks, $methodName . '()');
            $methodFilename = $_method->getFileName() ?: null;
            $methodNamespace = $_method->getDeclaringClass()->getNamespaceName();

            // Variables can't be passed to __callStatic by reference, so if
            // this method has any parameters that are passed by reference, it
            // needs a declared facade
            $declare = (bool) array_filter(
                $_params,
                fn(ReflectionParameter $p) =>
                    $p->isPassedByReference()
            );
            $internal = (bool) ($phpDoc->TagsByName['internal'] ?? null);
            $link = !$internal && $phpDoc && $phpDoc->hasDetail();
            $returnsVoid = false;

            if ($_method->isConstructor()) {
                continue;
            } else {
                if (isset($phpDoc->TagsByName['deprecated'])) {
                    continue;
                }
                $method = $methodName;
                if (strpos($method, '__') === 0 ||
                        in_array($method, $this->SkipMethods)) {
                    continue;
                }

                $_type = $phpDoc->Return->Type ?? null;
                if ($_type) {
                    $type = $this->getPhpDocTypeAlias(
                        $phpDoc->Return,
                        $phpDoc->Templates,
                        $methodNamespace,
                        $methodFilename
                    );
                } else {
                    $type = $_method->hasReturnType()
                        ? Reflect::getTypeDeclaration(
                            $_method->getReturnType(),
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $methodFilename, false)
                        )
                        : 'mixed';

                    // If the underlying method has more type information,
                    // provide a link to it
                    //
                    // @phpstan-ignore-next-line
                    if ($_type) {
                        $link = !$internal;
                    }
                }

                switch ($type) {
                    case '\static':
                    case 'static':
                    case '$this':
                        $type = $service;
                        break;
                    case '\self':
                    case 'self':
                        $type = $this->getTypeAlias($declaring);
                        break;
                    case 'void':
                        $returnsVoid = true;
                        break;
                }
                $summary = $phpDoc->Summary ?? null;
                $summary = $summary
                    ? ($declare || !$link ? $summary : "$summary (see {@see " . $getMethodFqsen() . '})')
                    : ($declare || !$link ? 'A facade for ' . $getMethodFqsen() : 'See {@see ' . $getMethodFqsen() . '}');

                // Convert "?<type>" to "<type>|null"
                if (!$declare && strpos($type, '?') === 0) {
                    $type = substr($type, 1) . '|null';
                }
            }

            $params = [];
            foreach ($_params as $_param) {
                $tag = $phpDoc->Params[$_param->getName()] ?? null;
                // Override the declared type if defined in the PHPDoc
                $_type = ($tag->Type ?? null)
                    ? $this->getPhpDocTypeAlias(
                        $tag,
                        $phpDoc->Templates,
                        $methodNamespace,
                        $methodFilename
                    )
                    : null;
                $params[] =
                    $declare
                        ? Reflect::getParameterPhpDoc(
                            $_param,
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $methodFilename, false),
                            $_type
                        )
                        : Reflect::getParameterDeclaration(
                            $_param,
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $methodFilename, false),
                            $_type,
                            null,
                            true
                        );
            }

            if (!$methods) {
                array_push($methods, ...$facadeMethods);
            }

            if ($declare) {
                $params = array_filter($params);
                $return = ($type && (!$_method->hasReturnType() ||
                        Reflect::getTypeDeclaration(
                            $_method->getReturnType(),
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $methodFilename, false)
                        ) !== $type))
                    ? "@return $type"
                    : '';

                $lines = [];
                $lines[] = $summary;
                $lines[] = '';
                if ($internal) {
                    $lines[] = '@internal';
                }
                if ($params) {
                    array_push($lines, ...$params);
                }
                if ($return) {
                    $lines[] = $return;
                }
                if ($link) {
                    $lines[] = '@see ' . $getMethodFqsen();
                }

                $toDeclare[] = [$_method, implode(\PHP_EOL, $lines), !$returnsVoid];
            } else {
                $methods[] = " * @method static $type $method("
                    . str_replace(\PHP_EOL, \PHP_EOL . ' * ', implode(', ', $params)) . ')'
                    . " $summary";
            }
        }
        $methods = implode(\PHP_EOL, $methods);

        $imports = $this->generateImports();

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        if ($methods) {
            $docBlock[] = $methods;
            $docBlock[] = ' *';
        }
        if ($this->ApiTag) {
            $docBlock[] = ' * @api';
            $docBlock[] = ' *';
        }
        $docBlock[] = " * @extends $extends<$service>";
        $docBlock[] = ' *';
        $docBlock[] = ' * @generated';
        $docBlock[] = ' */';

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $facadeNamespace;",
            implode(\PHP_EOL, $imports),
            implode(\PHP_EOL, $docBlock) . \PHP_EOL
                . "final class $facadeClass extends $extends" . \PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        if (!$facadeNamespace) {
            unset($blocks[2]);
        }

        $lines = [implode(\PHP_EOL . \PHP_EOL, $blocks)];

        array_push(
            $lines,
            ...$this->indent($this->generateGetter(
                'getService',
                "$service::class",
                '@inheritDoc',
                'string',
                self::VISIBILITY_PROTECTED
            ))
        );

        /** @var ReflectionMethod $_method */
        foreach ($toDeclare as [$_method, $docBlock, $return]) {
            $_params = $_method->getParameters();
            $return = $return ? 'return ' : '';
            $code = sprintf(
                '%sstatic::getInstance()->%s(%s);',
                $return,
                $_method->getName(),
                implode(', ', array_map(
                    fn(ReflectionParameter $p) =>
                        ($p->isVariadic() ? '...' : '') . '$' . $p->getName(),
                    $_params
                ))
            );

            array_push(
                $lines,
                '',
                ...$this->indent($this->generateMethod(
                    $_method->getName(),
                    $code,
                    $_params,
                    $_method->getReturnType(),
                    $docBlock
                ))
            );
        }

        $lines[] = '}';

        $this->handleOutput($lines);
    }
}
