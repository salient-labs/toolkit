<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use JsonSerializable;

/**
 * @internal
 */
class NamespaceData implements JsonSerializable
{
    public string $Name;
    /** @var array<string,ClassData> */
    public array $Classes = [];
    /** @var array<string,ClassData> */
    public array $Interfaces = [];
    /** @var array<string,ClassData> */
    public array $Traits = [];
    /** @var array<string,ClassData> */
    public array $Enums = [];

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $delimiter = $this->Name === '' ? '' : '\\';
        foreach ([
            'class' => $this->Classes,
            'interface' => $this->Interfaces,
            'trait' => $this->Traits,
            'enum' => $this->Enums,
        ] as $type => $classes) {
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
