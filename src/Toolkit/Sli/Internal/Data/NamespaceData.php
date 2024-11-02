<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Sli\Internal\NavigableToken;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Utility\File;
use Salient\Utility\Get;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;

/**
 * @internal
 */
class NamespaceData implements JsonSerializable
{
    /**
     * Type => [ NamespaceData property, heading ]
     */
    public const TYPE = [
        'class' => ['Classes', 'Class'],
        'interface' => ['Interfaces', 'Interface'],
        'trait' => ['Traits', 'Trait'],
        'enum' => ['Enums', 'Enum'],
    ];

    public const TYPE_MAP = [
        \T_CLASS => 'class',
        \T_INTERFACE => 'interface',
        \T_TRAIT => 'trait',
        \T_ENUM => 'enum',
    ];

    public string $Name;
    /** @var array<string,ClassData> */
    public array $Classes = [];
    /** @var array<string,ClassData> */
    public array $Interfaces = [];
    /** @var array<string,ClassData> */
    public array $Traits = [];
    /** @var array<string,ClassData> */
    public array $Enums = [];

    final public function __construct(string $name)
    {
        $this->Name = $name;
    }

    /**
     * @param (callable(ClassData|ConstantData|PropertyData|MethodData $data, string $type): bool)|null $filter
     * @param array<string,static>|null $data
     * @param array<class-string,ClassData>|null $index
     */
    public static function fromExtractor(
        TokenExtractor $extractor,
        ?callable $filter = null,
        ?ConsoleWriterInterface $console = null,
        ?array &$data = null,
        ?array &$index = null
    ): void {
        if ($extractor->getParent()) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Invalid token extractor');
            // @codeCoverageIgnoreEnd
        }

        $file = $extractor->getFilename();
        foreach ($extractor->getNamespaces() as $ns => $extractor) {
            $delimiter = $ns === '' ? '' : '\\';
            unset($aliases);
            foreach ($extractor->getClasses() as $className => $classExtractor) {
                /** @var NavigableToken */
                $token = $classExtractor->getClassToken();
                $type = self::TYPE_MAP[$token->id];
                $property = self::TYPE[$type][0];
                $exists = $type . '_exists';
                /** @var class-string */
                $fqcn = $ns . $delimiter . $className;
                $_fqcn = Get::fqcn($fqcn);

                if (isset($index[$_fqcn])) {
                    continue;
                }

                if (!$exists($fqcn)) {
                    !$console || $console->warn(
                        sprintf('Cannot load %s:', $type),
                        sprintf('%s (in %s:%d)', $fqcn, $file ?? '<code>', $token->line)
                    );
                    continue;
                }

                $class = new ReflectionClass($fqcn);
                if ($file !== null) {
                    $_file = $class->getFileName();
                    if ($_file === false || !File::same($_file, $file)) {
                        if ($_file === false) {
                            $_file = '<internal>';
                        }
                        !$console || $console->warn(
                            sprintf('Skipping %s loaded from:', $type),
                            sprintf('%s (expected %s)', $_file, $file)
                        );
                        continue;
                    }
                }

                $classData = ClassData::fromExtractor(
                    $classExtractor,
                    $class,
                    $aliases ??= self::getAliases($extractor),
                    $filter,
                    $console,
                );

                $index[$_fqcn] = $classData;

                if ($filter && !$filter($classData, $type)) {
                    continue;
                }

                $data[$ns] ??= new static($ns);
                $data[$ns]->$property[$className] = $classData;
            }
        }
    }

    /**
     * @return array<string,class-string>
     */
    private static function getAliases(TokenExtractor $extractor): array
    {
        foreach ($extractor->getImports() as $alias => [$type, $import]) {
            if ($type !== \T_CLASS) {
                continue;
            }
            /** @var class-string $import */
            $aliases[$alias] = $import;
        }
        return $aliases ?? [];
    }

    public function sort(ClassDataFactory $factory): void
    {
        foreach (self::TYPE as [$property]) {
            /** @var array<string,ClassData> */
            $classes = &$this->$property;
            if (!$classes) {
                continue;
            }
            uksort($classes, 'strcasecmp');
            foreach ($classes as $classData) {
                $classData->sort($factory);
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $delimiter = $this->Name === '' ? '' : '\\';
        foreach (self::TYPE as $type => [$property]) {
            /** @var array<string,ClassData> */
            $classes = $this->$property;
            foreach ($classes as $class => $classData) {
                $classData = [
                    'type' => $type,
                    'name' => $this->Name . $delimiter . $class,
                ] + $classData->jsonSerialize();
                $data[$class] = $classData;
            }
        }

        return $data ?? [];
    }
}
