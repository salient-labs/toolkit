<?php declare(strict_types=1);

namespace Salient\Sli\Command\Generate;

use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Core\Instantiable;
use Salient\Core\Facade\Facade;
use Salient\Core\Reflection\MethodReflection;
use Salient\PHPDoc\PHPDoc;
use Salient\Sli\EnvVar;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Str;

final class GenerateFacade extends AbstractGenerateCommand
{
    private const SKIP = [
        'offsetExists',
        'offsetGet',
        'offsetSet',
        'offsetUnset',
        'withFacade',
        'withoutFacade',
        // `Facade` methods:
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
    private array $ServiceFqcn = [];
    private string $FacadeFqcn = '';
    /** @var string[] */
    private array $Skip = [];
    /** @var string[] */
    private array $ImplementFqcn = [];
    private bool $SkipDeprecated = false;

    public function getDescription(): string
    {
        return 'Generate a facade';
    }

    protected function getOptionList(): iterable
    {
        yield from [
            CliOption::build()
                ->name('class')
                ->valueName('class')
                ->description("The facade's underlying class")
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            CliOption::build()
                ->name('service')
                ->valueName('service')
                ->description('Zero or more inheritors of <class>, in order of preference')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()
                ->bindTo($this->ServiceFqcn),
            CliOption::build()
                ->name('facade')
                ->valueName('facade')
                ->description('The facade to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->FacadeFqcn),
            CliOption::build()
                ->long('skip')
                ->short('k')
                ->valueName('method')
                ->description('Exclude a method from the facade')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Skip),
            CliOption::build()
                ->long('implement')
                ->short('I')
                ->valueName('interface')
                ->description('Implement an interface from the facade')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->ImplementFqcn),
            CliOption::build()
                ->long('skip-deprecated')
                ->short('E')
                ->description('Exclude deprecated methods from the facade')
                ->bindTo($this->SkipDeprecated),
        ];
        yield from $this->getGlobalOptionList('facade');
    }

    protected function run(string ...$args)
    {
        $this->startRun();

        $skip = array_merge($this->Skip, self::SKIP);

        $classFqcn = $this->requireFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $classClass,
        );

        $serviceFqcn = $this->requireFqcnOptionValues(
            'service',
            $this->ServiceFqcn,
        );

        $this->requireFqcnOptionValue(
            'facade',
            $this->FacadeFqcn,
            EnvVar::NS_FACADE,
            $facadeClass,
            $facadeNamespace,
        );

        $implementFqcn = $this->requireFqcnOptionValues(
            'interface',
            $this->ImplementFqcn,
        );

        $this->assertClassImplements($classFqcn, Instantiable::class);

        $this->OutputClass = $facadeClass;
        $this->OutputNamespace = $facadeNamespace;

        $this->loadInputClass($classFqcn);

        $classPrefix = $this->getClassPrefix();

        $service = $this->getFqcnAlias($classFqcn, $this->getPreferredAlias($classClass));
        $extends = $this->getFqcnAlias(Facade::class);

        $this->Extends[] = $extends;
        $this->Tags['extends'][] = sprintf('@extends %s<%s>', $extends, $service);

        foreach ($implementFqcn as $implementFqcn) {
            $this->Implements[] = $this->getFqcnAlias($implementFqcn);
        }

        $this->Modifiers[] = 'final';

        $services = [];
        foreach ($serviceFqcn as $serviceFqcn) {
            $serviceClass = Get::basename($serviceFqcn);
            $services[] = $this->getFqcnAlias($serviceFqcn, $this->getPreferredAlias($serviceClass));
        }

        $this->Desc ??= sprintf('A facade for %s', $classClass);

        $methods = $this->InputClass->getMethods(MethodReflection::IS_PUBLIC);
        usort(
            $methods,
            fn(MethodReflection $a, MethodReflection $b) =>
                $a->name <=> $b->name,
        );

        $blocks[] = $this->generateGetter(
            'getService',
            $this->code($services
                ? [$service, Arr::unwrap($services)]
                : $service),
            '@internal',
            null,
            self::VISIBILITY_PROTECTED,
        );

        foreach ($methods as $method) {
            $name = $method->name;
            $phpDoc = PHPDoc::forMethod($method, $this->InputClass);
            if (
                Str::startsWith($name, '__')
                || in_array($name, $skip, true)
                || $phpDoc->hasTag('internal')
                || ($this->SkipDeprecated && $phpDoc->hasTag('deprecated'))
            ) {
                continue;
            }
            $summary = $phpDoc->getSummary();
            $hasDetail = $phpDoc->hasDetail();
            $getFqsen = fn() =>
                $this->getTypeAlias($method->getDeclaringClass()->name) . '::' . $name . '()';
            $params = $method->getParameters();
            if ($method->getParameterIndex()->PassedByReference) {
            } else {
                if ($summary === null) {
                    $summary = $hasDetail
                        ? 'See {@see ' . $getFqsen() . '}'
                        : 'Call ' . $getFqsen() . " on the facade's underlying instance, loading it if necessary";
                } elseif ($hasDetail) {
                    $summary .= ' (see {@see ' . $getFqsen() . '})';
                }
                $this->addMagicMethod($name, $params, $method, $summary, $phpDoc, true);
            }
        }

        $this->handleOutput($this->generate($blocks));
    }

    private function getPreferredAlias(string $alias, string $suffix = 'Service'): string
    {
        return strcasecmp($alias, $this->OutputClass)
            ? $alias
            : $alias . $suffix;
    }
}
