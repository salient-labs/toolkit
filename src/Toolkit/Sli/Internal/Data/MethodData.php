<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use JsonSerializable;
use stdClass;

/**
 * @internal
 */
class MethodData implements JsonSerializable
{
    public string $Name;
    /** @var array<string,string> */
    public array $Templates = [];
    public ?string $Summary = null;
    public bool $Api = false;
    public bool $Internal = false;
    public bool $Deprecated = false;
    public bool $Declared = false;
    public bool $HasDocComment = false;
    public bool $Inherited = false;
    /** @var array{class-string,string}|null */
    public ?array $InheritedFrom = null;
    /** @var array{class-string,string}|null */
    public ?array $Prototype = null;
    public bool $IsAbstract = false;
    public bool $IsFinal = false;
    public bool $IsPublic = false;
    public bool $IsProtected = false;
    public bool $IsPrivate = false;
    public bool $IsStatic = false;
    /** @var string[] */
    public array $Modifiers = [];
    /** @var array<string,array{string,string}> */
    public array $Parameters = [];
    public ?string $ReturnType = null;
    public ?int $Line = null;
    public ?int $Lines = null;

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
            'templates' => $this->Templates ?: new stdClass(),
            'summary' => $this->Summary,
            'api' => $this->Api,
            'internal' => $this->Internal,
            'deprecated' => $this->Deprecated,
            'declared' => $this->Declared,
            'hasDocComment' => $this->HasDocComment,
            'inherited' => $this->Inherited,
            'inheritedFrom' => $this->InheritedFrom,
            'prototype' => $this->Prototype,
            'abstract' => $this->IsAbstract,
            'final' => $this->IsFinal,
            'public' => $this->IsPublic,
            'protected' => $this->IsProtected,
            'private' => $this->IsPrivate,
            'static' => $this->IsStatic,
            'modifiers' => $this->Modifiers,
            'parameters' => $this->Parameters ?: new stdClass(),
            'returnType' => $this->ReturnType,
            'line' => $this->Line,
            'lines' => $this->Lines,
        ];
    }
}
