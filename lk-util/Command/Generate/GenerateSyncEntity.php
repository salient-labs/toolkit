<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliOption;
use Lkrms\Concern\HasParent;
use Lkrms\Contract\ITreeable;
use Lkrms\Http\Catalog\HttpRequestMethod;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\DeferredEntity;
use Lkrms\Sync\Support\DeferredRelationship;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Inflect;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use Closure;
use DateTimeImmutable;

/**
 * Generates sync entities
 */
class GenerateSyncEntity extends GenerateCommand
{
    /**
     * @var mixed[]|null
     */
    public ?array $Entity;

    private string $ClassFqcn = '';

    private string $MemberVisibility = '';

    /**
     * @var string[]
     */
    private array $OneToOneRelationships = [];

    /**
     * @var string[]
     */
    private array $OneToManyRelationships = [];

    private ?string $ParentProperty = null;

    private ?string $ChildrenProperty = null;

    private ?string $ReferenceEntityFile = null;

    /**
     * @var class-string<HttpSyncProvider>|null
     */
    private ?string $Provider = null;

    private ?string $HttpEndpoint = null;

    private string $HttpMethod = '';

    /**
     * @var string[]
     */
    private array $HttpQuery = [];

    private ?string $HttpDataFile = null;

    /**
     * @var string[]
     */
    private array $SkipProperties = [];

    public function description(): string
    {
        return 'Generate a sync entity class';
    }

    protected function getOptionList(): array
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

`--children` must also be given. The generated class will implement `ITreeable`.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ParentProperty),
            CliOption::build()
                ->long('children')
                ->valueName('property')
                ->description(<<<EOF
Add a one-to-many "children" relationship to the entity

`--parent` must also be given. The generated class will implement `ITreeable`.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ChildrenProperty),
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
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
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
            ...$this->getOutputOptionList('entity'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->reset();

        $this->Entity = null;

        $fqcn = $this->getRequiredFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $class,
            $namespace
        );

        $this->OutputClass = $class;
        $this->OutputNamespace = $namespace;

        if ($this->ParentProperty !== null xor
                $this->ChildrenProperty !== null) {
            throw new CliInvalidArgumentsException(
                '--parent and --children must be used together'
            );
        }

        $this->Extends[] = $this->getFqcnAlias(SyncEntity::class);
        if ($this->ParentProperty !== null) {
            $this->Implements[] = $this->getFqcnAlias(ITreeable::class);
            $this->Uses[] = $this->getFqcnAlias(HasParent::class);
        }

        if ($this->Description === null) {
            $this->Description = sprintf(
                'Represents the state of %s %s entity in a backend',
                Inflect::indefinite($class),
                $class,
            );
        }

        $visibility = $this->MemberVisibility;
        $json = $this->ReferenceEntityFile;

        $provider = $this->Provider;
        if ($provider !== null) {
            /** @var HttpSyncProvider */
            $provider = $this->getProvider($provider, HttpSyncProvider::class);
        }

        $properties = ['Id' => 'int|string|null'];
        $oneToOne = [];
        $oneToMany = [];
        $parent = [];
        $children = [];

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
            $query = Convert::queryToData($this->HttpQuery) ?: null;
            $data = $this->HttpDataFile === null
                ? null
                : $this->getJson($this->HttpDataFile, $dataUri, false);
            $method = $data !== null && $endpoint !== null
                ? HttpRequestMethod::POST
                : $this->HttpMethod;
            $endpoint = $endpoint === null
                ? '/' . Str::toKebabCase($class)
                : $endpoint;

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

        $entityClass = new class extends SyncEntity {
            /**
             * @var string
             */
            public static $EntityName;

            protected static function getRemovablePrefixes(): ?array
            {
                return [self::$EntityName];
            }
        };

        $entityClass::$EntityName = $class;
        $normaliser = $entityClass::normaliser();
        $normaliser =
            fn(string $name): string =>
                Str::toPascalCase($normaliser($name));

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

            foreach ($entity as $key => $value) {
                if (!is_string($key) || !Pcre::match('/^[[:alpha:]]/', $key)) {
                    continue;
                }

                $key = $normaliser($key);

                if (in_array($key, $skip, true)) {
                    continue;
                }

                // Don't limit `Id` to one type
                if (array_key_exists($key, $properties)) {
                    continue;
                }

                if ($provider &&
                        is_string($value) &&
                        $provider->dateFormatter()->parse($value)) {
                    $properties[$key] = $this->getFqcnAlias(DateTimeImmutable::class, 'DateTime') . '|null';
                    continue;
                }

                if ((is_int($value) || is_string($value) || $value === null) &&
                        Pcre::match('/^(?<class>[[:alpha:]_][[:alnum:]_]*)Id$/', $key, $matches)) {
                    $key = $matches['class'];
                    $properties[$key] = "$key|null";
                    $tentativeOneToOne[$key] = $key;
                    continue;
                }

                if (Arr::ofArrayKey($value, true) &&
                        Pcre::match('/^(?<class>[[:alpha:]_][[:alnum:]_]*)Ids$/', $key, $matches)) {
                    $key = $matches['class'];
                    $properties[$key] = "{$key}[]|null";
                    $tentativeOneToMany[$key] = $key;
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
            $relationshipTypeAlias = $this->getFqcnAlias(RelationshipType::class);
        }

        $docBlock = [];
        if ($visibility === 'protected') {
            foreach ($properties as $property => $type) {
                $docBlock[] = "@property $type \$$property";
            }
            $docBlock[] = '';
        }

        if ($docBlock) {
            $this->PhpDoc = implode(\PHP_EOL, $docBlock);
        }

        $blocks = [];

        foreach ($properties as $property => $type) {
            $blocks[] = <<<EOF
/**
 * @var $type
 */
$visibility \$$property;
EOF;
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
                    "'%s' => [%s::%s => %s::class],",
                    $property,
                    $relationshipTypeAlias,
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
        if (!Pcre::match(
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
        $class = $normaliser($matches['class']);
        $array[$property] = $class;
    }
}
