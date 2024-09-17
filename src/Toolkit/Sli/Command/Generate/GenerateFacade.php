<?php declare(strict_types=1);

namespace Salient\Sli\Command\Generate;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Core\AbstractFacade;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtility;
use Salient\Sli\EnvVar;
use Salient\Utility\Arr;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Generates facades
 */
final class GenerateFacade extends AbstractGenerateCommand
{
    /**
     * Methods that shouldn't be surfaced by the Facade
     */
    private const SKIP_METHODS = [
        'getReadableProperties',
        'getWritableProperties',
        'offsetExists',
        'offsetGet',
        'offsetSet',
        'offsetUnset',
        'withFacade',
        'withoutFacade',
        // These are displaced by Facade
        'getInstance',
        'getService',
        'isLoaded',
        'load',
        'swap',
        'unload',
        'unloadAll',
    ];

    private string $ClassFqcn = '';
    /** @var string[] */
    private array $AliasFqcn = [];
    private string $FacadeFqcn = '';
    /** @var string[] */
    private array $SkipMethods = [];

    public function getDescription(): string
    {
        return 'Generate a facade';
    }

    protected function getOptionList(): iterable
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
                ->long('alias')
                ->valueName('alias_class')
                ->description('Map <class> to one or more compatible implementations')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()
                ->bindTo($this->AliasFqcn),
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
            ...$this->getGlobalOptionList('facade'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->startRun();

        $this->SkipMethods = array_merge($this->SkipMethods, self::SKIP_METHODS);

        $classFqcn = $this->requireFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $classClass
        );

        $aliasFqcn = $this->requireFqcnOptionValues('alias', $this->AliasFqcn);

        $this->requireFqcnOptionValue(
            'facade',
            $this->FacadeFqcn,
            EnvVar::NS_FACADE,
            $facadeClass,
            $facadeNamespace
        );

        $this->assertClassExists($classFqcn);

        $this->OutputClass = $facadeClass;
        $this->OutputNamespace = $facadeNamespace;

        $this->loadInputClass($classFqcn);

        $classPrefix = $this->getClassPrefix();

        $service = $this->getFqcnAlias($classFqcn, $classClass);
        $extends = $this->getFqcnAlias(AbstractFacade::class);

        $alias = [];
        foreach ($aliasFqcn as $aliasFqcn) {
            $alias[] = $this->getFqcnAlias($aliasFqcn);
        }

        $this->Description ??= sprintf(
            'A facade for %s',
            $service,
        );

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
        $methods = [];
        $toDeclare = [];
        foreach ($_methods as $_method) {
            $declaring = $_method->getDeclaringClass()->getName();
            $methodName = $_method->getName();
            $getMethodFqsen = fn() => $this->getTypeAlias($declaring) . "::{$methodName}()";
            $_params = $_method->getParameters();
            $docBlocks = PHPDocUtility::getAllMethodDocComments($_method, null, $classDocBlocks);
            $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks, $methodName . '()');
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
            $internal = isset($phpDoc->TagsByName['internal']);
            $link = !$internal && $phpDoc && $phpDoc->hasDetail();
            $returnsVoid = false;

            if ($_method->isConstructor()) {
                continue;
            } else {
                if (isset($phpDoc->TagsByName['deprecated'])) {
                    continue;
                }
                $method = $methodName;
                if (strpos($method, '__') === 0
                        || in_array($method, $this->SkipMethods)) {
                    continue;
                }

                $_type = $phpDoc && $phpDoc->Return ? $phpDoc->Return->getType() : null;
                if ($_type !== null) {
                    /** @var PHPDoc $phpDoc */
                    $type = $this->getPHPDocTypeAlias(
                        $phpDoc->Return,
                        $phpDoc->Templates,
                        $methodNamespace,
                        $methodFilename
                    );
                } else {
                    $type = $_method->hasReturnType()
                        ? PHPDocUtility::getTypeDeclaration(
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
                    : ($declare || !$link ? 'Call ' . $getMethodFqsen() . " on the facade's underlying instance, loading it if necessary" : 'See {@see ' . $getMethodFqsen() . '}');

                // Convert "?<type>" to "<type>|null"
                if (!$declare && strpos($type, '?') === 0) {
                    $type = substr($type, 1) . '|null';
                }
            }

            $params = [];
            foreach ($_params as $_param) {
                $tag = $phpDoc->Params[$_param->getName()] ?? null;
                // Override the declared type if defined in the PHPDoc
                if ($tag && $tag->getType() !== null) {
                    /** @var PHPDoc $phpDoc */
                    $_type = $this->getPHPDocTypeAlias(
                        $tag,
                        $phpDoc->Templates,
                        $methodNamespace,
                        $methodFilename
                    );
                } else {
                    $_type = null;
                }
                $params[] =
                    $declare
                        ? PHPDocUtility::getParameterPHPDoc(
                            $_param,
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $methodFilename, false),
                            $_type
                        )
                        : PHPDocUtility::getParameterDeclaration(
                            $_param,
                            $classPrefix,
                            fn(string $type): ?string =>
                                $this->getTypeAlias($type, $methodFilename, false),
                            $_type,
                            null,
                            true
                        );
            }

            if ($declare) {
                $params = array_filter($params);
                $return = ($type && (!$_method->hasReturnType()
                        || PHPDocUtility::getTypeDeclaration(
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
        if ($this->Description !== '') {
            $docBlock[] = " * {$this->Description}";
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
                $this->code($alias
                    ? [$service => Arr::unwrap($alias)]
                    : $service),
                '@internal',
                null,
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
