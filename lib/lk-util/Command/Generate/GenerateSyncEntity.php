<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliOption;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\Support\Catalog\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Test;
use DateTimeImmutable;
use RuntimeException;

/**
 * Generates SyncEntity subclasses from reference entities
 */
class GenerateSyncEntity extends GenerateCommand
{
    private const METHODS = [
        'get' => HttpRequestMethod::GET,
        'post' => HttpRequestMethod::POST,
    ];

    private const DEFAULT_METHOD = 'get';

    private ?string $ClassFqcn;

    private ?string $MemberVisibility;

    private ?string $ReferenceEntityFile;

    /**
     * @var class-string<HttpSyncProvider>|null
     */
    private ?string $Provider;

    private ?string $HttpEndpoint;

    private ?string $HttpMethod;

    /**
     * @var string[]|null
     */
    private ?array $HttpQuery;

    private ?string $HttpDataFile;

    public function description(): string
    {
        return 'Generate a sync entity class';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('generate')
                ->valueName('class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            ...$this->getOutputOptionList('entity'),
            CliOption::build()
                ->long('visibility')
                ->short('v')
                ->valueName('keyword')
                ->description("The visibility of the entity's properties")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['public', 'protected', 'private'])
                ->defaultValue('public')
                ->bindTo($this->MemberVisibility),
            CliOption::build()
                ->long('json')
                ->short('j')
                ->valueName('file')
                ->description('The path to a JSON-serialized reference entity')
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->ReferenceEntityFile),
            CliOption::build()
                ->long('provider')
                ->short('p')
                ->valueName('class')
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
                ->allowedValues(array_keys(self::METHODS))
                ->defaultValue(self::DEFAULT_METHOD)
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
                ->bindTo($this->HttpDataFile),
        ];
    }

    protected function run(string ...$args)
    {
        $fqcn = $this->getRequiredFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $class,
            $namespace
        );

        $this->OutputClass = $class;
        $this->OutputNamespace = $namespace;

        $extends = $this->getFqcnAlias(SyncEntity::class);

        $desc = $this->OutputDescription;
        $visibility = $this->MemberVisibility;
        $json = $this->ReferenceEntityFile;

        if ($provider = $this->Provider) {
            /** @var HttpSyncProvider */
            $provider = $this->getProvider($provider, HttpSyncProvider::class);
        }

        $props = ['Id' => 'int|string|null'];
        $entity = null;
        $entityUri = null;
        $data = null;
        $dataUri = null;

        if (!$fqcn) {
            throw new CliInvalidArgumentsException("invalid class: $fqcn");
        }

        if ($json) {
            $entity = $this->getJson($json, $entityUri);

            if (is_null($entity)) {
                throw new RuntimeException("Could not decode $json");
            }
        } elseif ($provider) {
            $endpoint = $this->HttpEndpoint;
            $query = Convert::queryToData($this->HttpQuery) ?: null;
            $data = $this->HttpDataFile;
            $data = $data ? $this->getJson($data, $dataUri, false) : null;
            $method = $data && $endpoint ? HttpRequestMethod::POST : self::METHODS[$this->HttpMethod];
            $endpoint = $endpoint ?: '/' . Convert::toKebabCase($class);

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

        if ($entity) {
            foreach (['data', 'Result', 'Items'] as $prop) {
                if (is_array($entity[$prop] ?? null)) {
                    $entity = $entity[$prop];
                    break;
                }
            }

            if (Test::isListArray($entity)) {
                $entity = $entity[0];
            }

            $typeMap = [
                'boolean' => 'bool',
                'integer' => 'int',
                'double' => 'float',
                'array' => 'mixed[]',
                'NULL' => 'mixed',
            ];

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

            foreach ($entity as $key => $value) {
                if (is_string($key) && preg_match('/^[[:alpha:]]/', $key)) {
                    $key = $normaliser($key);
                    $key = Convert::toPascalCase($key);

                    // Don't limit `Id` to one type
                    if (array_key_exists($key, $props)) {
                        continue;
                    }

                    if ($provider &&
                            is_string($value) &&
                            !is_null($provider->dateFormatter()->parse($value))) {
                        $props[$key] = $this->getFqcnAlias(DateTimeImmutable::class, 'DateTime') . '|null';
                        continue;
                    }

                    $type = gettype($value);
                    $type = $typeMap[$type] ?? $type;
                    $type .= $type == 'mixed' ? '' : '|null';

                    $props[$key] = $type;
                }
            }
        }

        $imports = $this->generateImports();

        $docBlock[] = '/**';
        if ($desc) {
            $docBlock[] = " * $desc";
            $docBlock[] = ' *';
        }
        if ($visibility == 'protected') {
            foreach ($props as $prop => $type) {
                $docBlock[] = " * @property $type \$$prop";
            }
            $docBlock[] = ' *';
        }
        if (!$this->NoMetaTags) {
            $values = [
                'stdout' => null,
                'force' => null,
                'json' => null,
                'provider' => null,
                'endpoint' => null,
                'method' => null,
                'query' => null,
                'data' => null,
            ];
            if ($provider) {
                unset($values['provider']);
            }
            if ($json) {
                $values['json'] = $entityUri ?: $this->ReferenceEntityFile;
            } elseif ($provider) {
                unset($values['endpoint'], $values['query']);
                $values['method'] = array_search($method ?? null, self::METHODS, true) ?: null;
                $values['data'] = $dataUri ?: $this->HttpDataFile;
            }
            $command = $this->getEffectiveCommandLine(true, $values);
            $program = array_shift($command);
            $docBlock[] = ' * @generated by ' . $program;
            $docBlock[] = ' * @salient-generate-command ' . implode(' ', $command);
        }
        $docBlock[] = ' */';
        if (count($docBlock) == 2) {
            $docBlock = null;
        }

        $blocks = [
            '<?php declare(strict_types=1);',
            "namespace $namespace;",
            implode(PHP_EOL, $imports),
            ($docBlock ? implode(PHP_EOL, $docBlock) . PHP_EOL : '')
                . "class $class extends $extends" . PHP_EOL
                . '{'
        ];

        if (!$imports) {
            unset($blocks[3]);
        }

        if (!$namespace) {
            unset($blocks[2]);
        }

        $lines = [implode(PHP_EOL . PHP_EOL, $blocks)];

        foreach ($props as $prop => $type) {
            $_lines = [
                '/**',
                " * @var $type",
                ' */',
                "$visibility \$$prop;",
            ];
            array_push($lines, ...array_map(fn($line) => '    ' . $line, $_lines), ...['']);
        }
        if (end($lines) === '') {
            array_pop($lines);
        }

        $lines[] = '}';

        $this->handleOutput($lines);
    }
}
