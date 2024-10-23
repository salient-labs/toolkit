<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use JsonSerializable;

/**
 * @internal
 */
class ClassData implements JsonSerializable
{
    public string $Name;
    /** @var array<string,string> */
    public array $Templates = [];
    public ?string $Summary = null;
    /** @var class-string[] */
    public array $Extends = [];
    /** @var class-string[] */
    public array $Implements = [];
    /** @var class-string[] */
    public array $Uses = [];
    public bool $Api = false;
    public bool $Internal = false;
    public bool $Deprecated = false;
    public bool $HasDocComment = false;
    public bool $IsAbstract = false;
    public bool $IsFinal = false;
    public bool $IsReadOnly = false;
    /** @var string[] */
    public array $Modifiers = [];
    /** @var array<string,ConstantData> */
    public array $Constants = [];
    /** @var array<string,PropertyData> */
    public array $Properties = [];
    /** @var array<string,MethodData> */
    public array $Methods = [];
    public ?string $File = null;
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
            'templates' => $this->Templates,
            'summary' => $this->Summary,
            'extends' => $this->Extends,
            'implements' => $this->Implements,
            'uses' => $this->Uses,
            'api' => $this->Api,
            'internal' => $this->Internal,
            'deprecated' => $this->Deprecated,
            'hasDocComment' => $this->HasDocComment,
            'abstract' => $this->IsAbstract,
            'final' => $this->IsFinal,
            'readonly' => $this->IsReadOnly,
            'modifiers' => $this->Modifiers,
            'constants' => $this->Constants,
            'properties' => $this->Properties,
            'methods' => $this->Methods,
            'file' => $this->File,
            'line' => $this->Line,
            'lines' => $this->Lines,
        ];
    }
}
