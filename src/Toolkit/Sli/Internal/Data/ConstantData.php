<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use JsonSerializable;

/**
 * @internal
 */
class ConstantData implements JsonSerializable
{
    public string $Name;
    public ?string $Summary = null;
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

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'summary' => $this->Summary,
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
