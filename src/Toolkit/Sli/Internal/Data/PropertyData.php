<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\PHPDoc\Tag\PropertyTag;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocUtil;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * @internal
 */
class PropertyData implements JsonSerializable
{
    use HasPHPDoc;
    use MemberDataTrait;

    public string $Name;
    public ClassData $Class;
    public ?string $Summary = null;
    public ?string $Description = null;
    public bool $Api = false;
    public bool $Internal = false;
    public bool $Deprecated = false;
    public bool $Magic = false;
    public bool $Declared = false;
    public bool $HasDocComment = false;
    public bool $Inherited = false;
    /** @var array{class-string,string}|null */
    public ?array $InheritedFrom = null;
    public bool $IsPublic = false;
    public bool $IsProtected = false;
    public bool $IsPrivate = false;
    public bool $IsStatic = false;
    public bool $IsReadOnly = false;
    public bool $IsWriteOnly = false;
    /** @var string[] */
    public array $Modifiers = [];
    public ?string $Type = null;
    public ?string $DefaultValue = null;
    public ?int $Line = null;

    final public function __construct(string $name, ClassData $class)
    {
        $this->Name = $name;
        $this->Class = $class;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    public static function fromReflection(
        ReflectionProperty $property,
        ReflectionClass $class,
        ClassData $classData,
        ?bool $declared = null,
        ?int $line = null,
        ?ConsoleWriterInterface $console = null
    ): self {
        $propertyName = $property->getName();
        try {
            $docBlocks = PHPDocUtil::getAllPropertyDocComments($property, $class, $classDocBlocks);
            $phpDoc = PHPDoc::fromDocBlocks($docBlocks, $classDocBlocks, "\${$propertyName}");
            self::checkPHPDoc($phpDoc, $console);
        } catch (Throwable $ex) {
            !$console || $console->exception($ex, Level::WARNING, null);
            $phpDoc = new PHPDoc();
        }

        $declaring = $property->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();

        $data = (new static($propertyName, $classData))->applyPHPDoc($phpDoc);
        $data->Declared = $declared ??= ($declaringName === $className);
        $data->Inherited = $declaringName !== $className;

        if ($declared) {
            $data->Line = $line;
        } elseif ($declaringName !== $className) {
            $data->InheritedFrom = [$declaringName, $propertyName];
        } elseif ($inserted = Reflect::getTraitProperty($declaring, $propertyName)) {
            $data->InheritedFrom = [
                $inserted->getDeclaringClass()->getName(),
                $inserted->getName(),
            ];
        }

        $data->Modifiers = array_keys(array_filter([
            'public' => $data->IsPublic = $property->isPublic(),
            'protected' => $data->IsProtected = $property->isProtected(),
            'private' => $data->IsPrivate = $property->isPrivate(),
            'static' => $data->IsStatic = $property->isStatic(),
            'readonly' => $data->IsReadOnly = \PHP_VERSION_ID >= 80100 && $property->isReadOnly(),
        ]));

        $vars = $phpDoc->getVars();
        if (count($vars) === 1 && array_key_first($vars) === 0) {
            $data->Type = $vars[0]->getType();
        } elseif ($property->hasType()) {
            $data->Type = PHPDocUtil::getTypeDeclaration(
                $property->getType(),
                '\\',
                fn($fqcn) => Get::basename($fqcn),
            );
        }

        $hasDefaultValue = false;
        $value = null;
        if (\PHP_VERSION_ID >= 80000) {
            if ($property->hasDefaultValue() && (
                ($value = $property->getDefaultValue()) !== null
                || $property->hasType()
            )) {
                $hasDefaultValue = true;
            }
        } elseif (array_key_exists(
            $propertyName,
            $values = $class->getDefaultProperties()
        ) && (
            ($value = $values[$propertyName]) !== null
            || $property->hasType()
        )) {
            $hasDefaultValue = true;
        }
        if ($hasDefaultValue) {
            $data->DefaultValue = self::getValueCode($value, $declared);
        }

        return $data;
    }

    public static function fromPropertyTag(
        PropertyTag $property,
        ClassData $classData,
        ?int $line = null
    ): self {
        $propertyName = $property->getName();

        $data = new static($propertyName, $classData);
        $data->Summary = $property->getDescription();
        $data->Magic = true;

        $class = $property->getClass();
        if ($class !== null && $class !== $classData->getFqcn()) {
            $data->InheritedFrom = [$class, $propertyName];
        } else {
            $data->Line = $line;
        }

        $data->Modifiers = array_keys(array_filter([
            'public' => $data->IsPublic = true,
            'readonly' => $data->IsReadOnly = $property->isReadOnly(),
        ]));
        $data->IsWriteOnly = $property->isWriteOnly();

        $data->Type = $property->getType();

        return $data;
    }

    public function getFqsen(): string
    {
        return "{$this->Class->getFqcn()}::\${$this->Name}";
    }

    public function getStructuralElementName(): string
    {
        return "\${$this->Name}";
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->Summary,
            'description' => $this->Description,
            'api' => $this->Api,
            'internal' => $this->Internal,
            'deprecated' => $this->Deprecated,
            'magic' => $this->Magic,
            'declared' => $this->Declared,
            'hasDocComment' => $this->HasDocComment,
            'inherited' => $this->Inherited,
            'inheritedFrom' => $this->InheritedFrom,
            'public' => $this->IsPublic,
            'protected' => $this->IsProtected,
            'private' => $this->IsPrivate,
            'static' => $this->IsStatic,
            'readonly' => $this->IsReadOnly,
            'writeonly' => $this->IsWriteOnly,
            'modifiers' => $this->Modifiers,
            'type' => $this->Type,
            'defaultValue' => $this->DefaultValue,
            'line' => $this->Line,
        ];
    }
}
