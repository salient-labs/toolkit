<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\PHPDoc\PHPDoc;
use Salient\Utility\Reflect;
use JsonSerializable;
use ReflectionClass;
use ReflectionClassConstant;
use Throwable;

/**
 * @internal
 */
class ConstantData implements JsonSerializable
{
    use HasPHPDoc;
    use MemberDataTrait;

    public string $Name;
    public ClassData $Class;
    public ?string $Summary = null;
    public ?string $Description = null;
    public bool $SummaryInherited = false;
    public bool $DescriptionInherited = false;
    public bool $Api = false;
    public bool $Internal = false;
    public bool $Deprecated = false;
    public bool $Declared = false;
    public bool $HasDocComment = false;
    public bool $Inherited = false;
    /** @var array{class-string,string}|null */
    public ?array $InheritedFrom = null;
    public bool $IsFinal = false;
    public bool $IsPublic = false;
    public bool $IsProtected = false;
    public bool $IsPrivate = false;
    /** @var string[] */
    public array $Modifiers = [];
    public ?string $Type = null;
    public string $Value = '';
    public ?int $Line = null;

    final public function __construct(string $name, ClassData $class)
    {
        $this->Name = $name;
        $this->Class = $class;
    }

    /**
     * @param ReflectionClass<*> $class
     * @param array<string,class-string> $aliases
     */
    public static function fromReflection(
        ReflectionClassConstant $constant,
        ReflectionClass $class,
        ClassData $classData,
        array $aliases = [],
        ?bool $declared = null,
        ?int $line = null,
        ?ConsoleWriterInterface $console = null
    ): self {
        $constantName = $constant->getName();
        try {
            $phpDoc = PHPDoc::forConstant($constant, $class, $aliases);
            self::checkPHPDoc($phpDoc, $console);
        } catch (Throwable $ex) {
            !$console || $console->exception($ex, Level::WARNING, null);
            $phpDoc = new PHPDoc();
        }

        $declaring = $constant->getDeclaringClass();
        $className = $class->getName();
        $declaringName = $declaring->getName();

        $data = (new static($constantName, $classData))->applyPHPDoc($phpDoc);
        $data->Declared = $declared ??= ($declaringName === $className);
        $data->Inherited = $declaringName !== $className;

        if ($declared) {
            $data->Line = $line;
        } elseif ($declaringName !== $className) {
            $data->InheritedFrom = [$declaringName, $constantName];
        } elseif ($inserted = Reflect::getTraitConstant($declaring, $constantName)) {
            $data->InheritedFrom = [
                $inserted->getDeclaringClass()->getName(),
                $inserted->getName(),
            ];
        }

        $data->Modifiers = array_keys(array_filter([
            'final' => $data->IsFinal = \PHP_VERSION_ID >= 80100 && $constant->isFinal(),
            'public' => $data->IsPublic = $constant->isPublic(),
            'protected' => $data->IsProtected = $constant->isProtected(),
            'private' => $data->IsPrivate = $constant->isPrivate(),
        ]));

        $vars = $phpDoc->getVars();
        if (count($vars) === 1 && array_key_first($vars) === 0) {
            $data->Type = $vars[0]->getType();
        }

        $data->Value = self::getValueCode($constant->getValue(), $declared);

        return $data;
    }

    public function getFqsen(): string
    {
        return "{$this->Class->getFqcn()}::{$this->Name}";
    }

    public function getStructuralElementName(): string
    {
        return $this->Name;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->Summary,
            'description' => $this->Description,
            'summaryInherited' => $this->SummaryInherited,
            'descriptionInherited' => $this->DescriptionInherited,
            'api' => $this->Api,
            'internal' => $this->Internal,
            'deprecated' => $this->Deprecated,
            'declared' => $this->Declared,
            'hasDocComment' => $this->HasDocComment,
            'inherited' => $this->Inherited,
            'inheritedFrom' => $this->InheritedFrom,
            'final' => $this->IsFinal,
            'public' => $this->IsPublic,
            'protected' => $this->IsProtected,
            'private' => $this->IsPrivate,
            'modifiers' => $this->Modifiers,
            'type' => $this->Type,
            'value' => $this->Value,
            'line' => $this->Line,
        ];
    }
}
