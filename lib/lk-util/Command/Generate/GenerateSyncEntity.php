<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Generate;

use DateTimeImmutable;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Test;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\LkUtil\Dictionary\EnvVar;
use Lkrms\Support\Dictionary\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncEntity;
use RuntimeException;

/**
 * Generates SyncEntity subclasses from reference entities
 *
 */
class GenerateSyncEntity extends GenerateCommand
{
    private const METHODS = [
        'get' => HttpRequestMethod::GET,
        'post' => HttpRequestMethod::POST,
    ];

    private const DEFAULT_METHOD = 'get';

    public function getShortDescription(): string
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
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->required(),
            CliOption::build()
                ->long('package')
                ->short('p')
                ->valueName('package')
                ->description('The PHPDoc package')
                ->optionType(CliOptionType::VALUE)
                ->envVariable('PHPDOC_PACKAGE'),
            CliOption::build()
                ->long('desc')
                ->short('d')
                ->valueName('description')
                ->description('A short description of the entity')
                ->optionType(CliOptionType::VALUE),
            CliOption::build()
                ->long('stdout')
                ->short('s')
                ->description('Write to standard output'),
            CliOption::build()
                ->long('force')
                ->short('f')
                ->description('Overwrite the class file if it already exists'),
            CliOption::build()
                ->long('no-meta')
                ->short('m')
                ->description("Suppress '@lkrms-*' metadata tags"),
            CliOption::build()
                ->long('visibility')
                ->short('v')
                ->valueName('keyword')
                ->description("The visibility of the entity's properties")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['public', 'protected', 'private'])
                ->defaultValue('public'),
            CliOption::build()
                ->long('json')
                ->short('j')
                ->valueName('file')
                ->description('The path to a JSON-serialized reference entity')
                ->optionType(CliOptionType::VALUE),
            CliOption::build()
                ->long('provider')
                ->short('i')
                ->valueName('class')
                ->description('The HttpSyncProvider class to retrieve a reference entity from')
                ->optionType(CliOptionType::VALUE)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value)),
            CliOption::build()
                ->long('endpoint')
                ->short('e')
                ->valueName('endpoint')
                ->description("The endpoint to retrieve a reference entity from, e.g. '/user'")
                ->optionType(CliOptionType::VALUE),
            CliOption::build()
                ->long('method')
                ->short('h')
                ->valueName('method')
                ->description('The HTTP method to use when requesting a reference entity')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::METHODS))
                ->defaultValue(self::DEFAULT_METHOD),
            CliOption::build()
                ->long('query')
                ->short('q')
                ->valueName('field=value')
                ->description('A query parameter to use when requesting a reference entity')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed(),
            CliOption::build()
                ->long('data')
                ->short('o')
                ->valueName('file')
                ->description('The path to JSON-serialized data to submit when requesting a reference entity')
                ->optionType(CliOptionType::VALUE),
        ];
    }

    protected function run(string ...$args)
    {
        $namespace = explode('\\', ltrim($this->getOptionValue('generate'), '\\'));
        $class = array_pop($namespace);
        $namespace = implode('\\', $namespace) ?: Env::get(EnvVar::NS_DEFAULT, '');
        $fqcn = $namespace ? $namespace . '\\' . $class : $class;
        $classPrefix = $namespace ? '\\' : '';

        $this->OutputClass = $class;
        $this->OutputNamespace = $namespace;
        $this->ClassPrefix = $classPrefix;

        $extends = $this->getFqcnAlias(SyncEntity::class);

        $package = $this->getOptionValue('package');
        $desc = $this->getOptionValue('desc');
        $visibility = $this->getOptionValue('visibility');
        $json = $this->getOptionValue('json');
        /** @var class-string<HttpSyncProvider>|null */
        $provider = $this->getOptionValue('provider');

        $props = ['Id' => 'int|string|null'];
        $entity = null;
        $entityUri = null;
        $data = null;
        $dataUri = null;

        if (!$fqcn) {
            throw new CliArgumentsInvalidException("invalid class: $fqcn");
        }

        if ($json) {
            $entity = $this->getJson($json, $entityUri);

            if (is_null($entity)) {
                throw new RuntimeException("Could not decode $json");
            }
        } elseif ($provider) {
            /** @var HttpSyncProvider */
            $provider = $this->getProvider($provider, HttpSyncProvider::class);
            $endpoint = $this->getOptionValue('endpoint');
            $query = Convert::queryToData($this->getOptionValue('query')) ?: null;
            $data = $this->getOptionValue('data');
            $data = $data ? $this->getJson($data, $dataUri) : null;
            $method = $data && $endpoint ? HttpRequestMethod::POST : self::METHODS[$this->getOptionValue('method')];
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
            foreach (['data', 'Result'] as $prop) {
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

                    if (is_string($value) &&
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

        $imports = $this->getImports();

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
        if ($package) {
            $docBlock[] = " * @package $package";
        }
        if (!$this->getOptionValue('no-meta')) {
            if ($entityUri) {
                $docBlock[] = " * @lkrms-reference-entity $entityUri";
            }
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
            if ($json) {
                $values['json'] = $entityUri ?: $this->getOptionValue('json');
            } elseif ($provider) {
                unset($values['provider'], $values['endpoint'], $values['query']);
                $values['method'] = array_search($method ?? null, self::METHODS, true) ?: null;
                $values['data'] = $dataUri ?: $this->getOptionValue('data');
            }
            $docBlock[] = ' * @lkrms-generate-command '
                . implode(' ', $this->getEffectiveCommandLine(true, $values));
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

        $this->handleOutput($class, $namespace, $lines);
    }
}
