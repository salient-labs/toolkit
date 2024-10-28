<?php declare(strict_types=1);

namespace Salient\Sli\Command\Generate;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Http\HttpRequestMethod;
use Salient\Core\Concern\TreeableTrait;
use Salient\Core\DateFormatter;
use Salient\Core\DateParser;
use Salient\Core\DotNetDateParser;
use Salient\Sli\EnvVar;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\AbstractSyncEntity;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use DateTimeInterface;

/**
 * Generates sync entities
 */
class GenerateSyncEntity extends AbstractGenerateCommand
{
    /** @var mixed[]|null */
    public ?array $Entity;
    private string $ClassFqcn = '';
    private string $MemberVisibility = '';
    /** @var string[] */
    private array $OneToOneRelationships = [];
    /** @var string[] */
    private array $OneToManyRelationships = [];
    private ?string $ParentProperty = null;
    private ?string $ChildrenProperty = null;
    /** @var string[] */
    private array $RemovablePrefixes = [];
    private ?string $ReferenceEntityFile = null;
    private ?string $Provider = null;
    private ?string $HttpEndpoint = null;
    private string $HttpMethod = '';
    /** @var string[] */
    private array $HttpQuery = [];
    private ?string $HttpDataFile = null;
    /** @var string[] */
    private array $SkipProperties = [];

    public function getDescription(): string
    {
        return 'Generate a sync entity class';
    }

    protected function getOptionList(): iterable
    {
        return [
            CliOption::build()
                ->long('class')
                ->valueName('class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            CliOption::build()
                ->long('visibility')
                ->short('v')
                ->valueName('visibility')
                ->description("The visibility of the entity's properties")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['public', 'protected', 'private'])
                ->defaultValue('public')
                ->bindTo($this->MemberVisibility),
            CliOption::build()
                ->long('one')
                ->valueName('property=class')
                ->description('Add a one-to-one relationship to the entity')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->OneToOneRelationships),
            CliOption::build()
                ->long('many')
                ->valueName('property=class')
                ->description('Add a one-to-many relationship to the entity')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->OneToManyRelationships),
            CliOption::build()
                ->long('parent')
                ->valueName('property')
                ->description(<<<EOF
Add a one-to-one "parent" relationship to the entity

`--children` must also be given. The generated class will implement `Treeable`.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ParentProperty),
            CliOption::build()
                ->long('children')
                ->valueName('property')
                ->description(<<<EOF
Add a one-to-many "children" relationship to the entity

`--parent` must also be given. The generated class will implement `Treeable`.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ChildrenProperty),
            CliOption::build()
                ->long('trim')
                ->valueName('prefix')
                ->description("Specify the entity's removable prefixes")
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->RemovablePrefixes),
            CliOption::build()
                ->long('json')
                ->short('j')
                ->valueName('file')
                ->description('The path to a JSON-serialized reference entity')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE)
                ->bindTo($this->ReferenceEntityFile),
            CliOption::build()
                ->long('provider')
                ->short('p')
                ->valueName('provider')
                ->description('The HttpSyncProvider class to retrieve a reference entity from')
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->Provider),
            CliOption::build()
                ->long('endpoint')
                ->short('e')
                ->valueName('endpoint')
                ->description("The endpoint to retrieve a reference entity from, e.g. '/user'")
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->HttpEndpoint),
            CliOption::build()
                ->long('method')
                ->short('h')
                ->valueName('method')
                ->description('The HTTP method to use when requesting a reference entity')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues([
                    HttpRequestMethod::GET,
                    HttpRequestMethod::POST,
                ])
                ->defaultValue(HttpRequestMethod::GET)
                ->bindTo($this->HttpMethod),
            CliOption::build()
                ->long('query')
                ->short('q')
                ->valueName('field=value')
                ->description('A query parameter to use when requesting a reference entity')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->HttpQuery),
            CliOption::build()
                ->long('data')
                ->short('J')
                ->valueName('file')
                ->description('The path to JSON-serialized data to submit when requesting a reference entity')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE)
                ->bindTo($this->HttpDataFile),
            CliOption::build()
                ->long('skip')
                ->short('k')
                ->description('Exclude a property from the entity')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->SkipProperties),
            ...$this->getGlobalOptionList('entity'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->startRun();

        $this->Entity = null;

        $fqcn = $this->requireFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $class,
            $namespace
        );

        $this->OutputClass = $class;
        $this->OutputNamespace = $namespace;

        if ($this->ParentProperty !== null
                xor $this->ChildrenProperty !== null) {
            throw new CliInvalidArgumentsException(
                '--parent and --children must be used together'
            );
        }

        $this->Extends[] = $this->getFqcnAlias(AbstractSyncEntity::class);
        if ($this->ParentProperty !== null) {
            $this->Implements[] = $this->getFqcnAlias(Treeable::class);
            $this->Uses[] = $this->getFqcnAlias(TreeableTrait::class);
        }

        $this->Description ??= sprintf(
            'Represents the state of %s %s entity in a backend',
            Inflect::indefinite($class),
            $class,
        );

        $visibility = $this->MemberVisibility;
        $json = $this->ReferenceEntityFile;

        $provider = $this->Provider;
        if ($provider !== null) {
            $provider = $this->getFqcnOptionInstance('provider', $provider, HttpSyncProvider::class, EnvVar::NS_PROVIDER);
        }

        $properties = ['Id' => 'int|string|null'];
        $oneToOne = [];
        $oneToMany = [];
        $parent = [];
        $children = [];
        $dates = [];

        $tentativeOneToOne = [];
        $tentativeOneToMany = [];

        $entity = null;
        $entityUri = null;
        $data = null;
        $dataUri = null;

        if ($json !== null) {
            $entity = $this->getJson($json, $entityUri);
            if (!is_array($entity)) {
                throw new CliInvalidArgumentsException(sprintf(
                    'Not a reference entity: %s',
                    $json,
                ));
            }
        } elseif ($provider) {
            $endpoint = $this->HttpEndpoint;
            $query = Get::filter($this->HttpQuery) ?: null;
            $data = $this->HttpDataFile === null
                ? null
                : $this->getJson($this->HttpDataFile, $dataUri, false);
            $method = $data !== null && $endpoint !== null
                ? HttpRequestMethod::POST
                : $this->HttpMethod;
            $endpoint ??= '/' . Str::kebab($class);

            $curler = $provider->getCurler($endpoint);
            $entityUri = $provider->getEndpointUrl($endpoint);

            switch ($method) {
                case HttpRequestMethod::GET:
                    $entity = $curler->get($query);
                    break;

                case HttpRequestMethod::POST:
                    $entity = $curler->post($data, $query);
                    break;
            }
        }

        $entityClass = new class extends AbstractSyncEntity {
            /** @var string[] */
            public static array $Prefixes;
            public static bool $Expand = true;

            protected static function getRemovablePrefixes(): ?array
            {
                return self::$Expand
                    ? parent::expandPrefixes(self::$Prefixes)
                    : self::$Prefixes;
            }
        };

        if ($this->RemovablePrefixes) {
            $entityClass::$Prefixes = $this->RemovablePrefixes;
            $entityClass::$Expand = false;
        } else {
            $entityClass::$Prefixes = [$class];
        }

        $normaliser = static fn(string $name): string =>
            Str::pascal($entityClass::normaliseProperty($name));
        $entityClass::flushStatic();

        $skip = [];
        foreach ($this->SkipProperties as $property) {
            $skip[] = $normaliser($property);
        }

        if ($entity) {
            foreach (['data', 'Result', 'Items'] as $property) {
                if (is_array($entity[$property] ?? null)) {
                    $entity = $entity[$property];
                    break;
                }
            }

            if (Arr::isList($entity)) {
                $entity = $entity[0];
            }

            $typeMap = [
                'boolean' => 'bool',
                'integer' => 'int',
                'double' => 'float',
                'array' => 'mixed[]',
                'NULL' => 'mixed',
            ];

            $dateFormatter = $provider
                ? $provider->getDateFormatter()
                : new DateFormatter(
                    DateTimeInterface::ATOM,
                    null,
                    new DateParser(),
                    new DotNetDateParser(),
                );

            foreach ($entity as $key => $value) {
                if (!is_string($key) || !Regex::match('/^[[:alpha:]]/', $key)) {
                    continue;
                }

                $key = $normaliser($key);

                if (in_array($key, $skip, true)) {
                    continue;
                }

                // Don't limit `Id` to one type
                if (isset($properties[$key])) {
                    continue;
                }

                if (is_string($value) && trim($value) !== '' && $dateFormatter->parse($value)) {
                    $properties[$key] = $this->getFqcnAlias(DateTimeInterface::class) . '|null';
                    $dates[] = $key;
                    continue;
                }

                if ((is_int($value) || is_string($value) || $value === null)
                        && Regex::match('/^(?<class>[[:alpha:]_][[:alnum:]_]*)Id$/', $key, $matches)) {
                    $key = $matches['class'];
                    $properties[$key] = "$key|null";
                    $tentativeOneToOne[$key] = $key;
                    continue;
                }

                if (Arr::ofArrayKey($value, true)
                        && Regex::match('/^(?<class>[[:alpha:]_][[:alnum:]_]*)Ids$/', $key, $matches)) {
                    $key = $matches['class'];
                    $properties[$key] = "{$key}[]|null";
                    $tentativeOneToMany[$key] = $key;
                    continue;
                }

                if (is_array($value) && Arr::ofString(array_keys($value))) {
                    $properties[$key] = 'array<string,mixed>|null';
                    continue;
                }

                $type = gettype($value);
                $type = $typeMap[$type] ?? $type;
                $type .= '|null';

                $properties[$key] = $type;
            }
        }

        $count = 0;
        if ($this->ParentProperty !== null) {
            $this->validateRelationship("{$this->ParentProperty}={$class}", $normaliser, $parent);
            $this->validateRelationship("{$this->ChildrenProperty}={$class}", $normaliser, $children);
            $count += 2;
        }
        foreach ($this->OneToOneRelationships as $value) {
            $this->validateRelationship($value, $normaliser, $oneToOne);
            $count++;
        }
        foreach ($this->OneToManyRelationships as $value) {
            $this->validateRelationship($value, $normaliser, $oneToMany);
            $count++;
        }
        if (count($oneToOne + $oneToMany + $parent + $children) !== $count) {
            throw new CliInvalidArgumentsException(
                'properties passed to --one, --many, --parent and --children must be unique'
            );
        }

        foreach ($parent as $key => $value) {
            $deferredEntity ??= $this->getFqcnAlias(DeferredEntity::class);
            $properties[$key] = sprintf('static|%s<static>|null', $deferredEntity);
        }
        foreach ($children as $key => $value) {
            $deferredEntity ??= $this->getFqcnAlias(DeferredEntity::class);
            $deferredRelationship ??= $this->getFqcnAlias(DeferredRelationship::class);
            $properties[$key] = sprintf(
                'array<static|%s<static>>|%s<static>|null',
                $deferredEntity,
                $deferredRelationship,
            );
        }
        foreach ($oneToOne as $key => $value) {
            $deferredEntity ??= $this->getFqcnAlias(DeferredEntity::class);
            $properties[$key] = sprintf(
                '%s|%s<%s>|null',
                $value,
                $deferredEntity,
                $value,
            );
        }
        foreach ($oneToMany as $key => $value) {
            $deferredEntity ??= $this->getFqcnAlias(DeferredEntity::class);
            $deferredRelationship ??= $this->getFqcnAlias(DeferredRelationship::class);
            $properties[$key] = sprintf(
                'array<%s|%s<%s>>|%s<%s>|null',
                $value,
                $deferredEntity,
                $value,
                $deferredRelationship,
                $value,
            );
        }

        $oneToOne += array_diff_key($tentativeOneToOne, $oneToMany, $parent, $children);
        $oneToMany += array_diff_key($tentativeOneToMany, $oneToOne, $parent, $children);
        $relationships = $oneToOne + $oneToMany;

        if ($relationships) {
            // Sort relationships by the position of their respective properties
            $relationships = array_replace(array_intersect_key($properties, $relationships), $relationships);
        }

        $docBlock = [];
        if ($visibility === 'protected') {
            foreach ($properties as $property => $type) {
                $docBlock[] = "@property $type \$$property";
            }
            $docBlock[] = '';
        }

        if ($docBlock) {
            $this->PHPDoc = implode(\PHP_EOL, $docBlock);
        }

        $blocks = [];

        foreach ($properties as $property => $type) {
            $blocks[] = $this->Collapse
                ? <<<EOF
/** @var $type */
$visibility \$$property;
EOF
                : <<<EOF
/**
 * @var $type
 */
$visibility \$$property;
EOF;
        }

        if ($this->Collapse) {
            $blocks = [implode(\PHP_EOL, $blocks)];
        }

        if ($parent) {
            $blocks[] = implode(\PHP_EOL, $this->generateGetter(
                'getParentProperty',
                $this->code(array_key_first($parent)),
                '@internal',
            ));
            $blocks[] = implode(\PHP_EOL, $this->generateGetter(
                'getChildrenProperty',
                $this->code(array_key_first($children)),
                '@internal',
            ));
        }

        if ($relationships) {
            $lines = [];
            foreach ($relationships as $property => $type) {
                $lines[] = sprintf(
                    "'%s' => [self::%s => %s::class],",
                    $property,
                    isset($oneToMany[$property]) ? 'ONE_TO_MANY' : 'ONE_TO_ONE',
                    $type,
                );
            }
            $blocks[] = implode(\PHP_EOL, $this->generateGetter(
                'getRelationships',
                '[' . \PHP_EOL . implode(\PHP_EOL, $this->indent($lines)) . \PHP_EOL . ']',
                '@internal',
                'array',
            ));
        }

        if ($dates) {
            $blocks[] = implode(\PHP_EOL, $this->generateGetter(
                'getDateProperties',
                $this->code($dates),
                '@internal',
                'array',
            ));
        }

        if ($this->RemovablePrefixes) {
            $blocks[] = implode(\PHP_EOL, $this->generateGetter(
                'getRemovablePrefixes',
                $this->code($this->RemovablePrefixes),
                <<<EOF
@internal

@return string[]
EOF,
                'array',
                self::VISIBILITY_PROTECTED,
            ));
        }

        $this->Entity = $entity;
        $this->handleOutput($this->generate($blocks));
    }

    /**
     * @param Closure(string): string $normaliser
     * @param array<string,string> $array
     * @param-out array<string,string> $array
     */
    private function validateRelationship(
        string $relationship,
        Closure $normaliser,
        array &$array
    ): void {
        if (!Regex::match(
            '/^(?<property>[[:alpha:]_][[:alnum:]_]*)=(?<class>[[:alpha:]_][[:alnum:]_]*)$/i',
            $relationship,
            $matches
        )) {
            throw new CliInvalidArgumentsException(sprintf(
                'Invalid relationship: %s',
                $relationship,
            ));
        }

        $property = $normaliser($matches['property']);
        $class = $matches['class'];
        $array[$property] = $class;
    }
}
