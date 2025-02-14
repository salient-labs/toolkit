<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

use Salient\Utility\Arr;

/**
 * @api
 */
class MethodTag extends AbstractTag
{
    /** @var array<string,MethodParam> */
    protected array $Params;
    protected bool $IsStatic;

    /**
     * @internal
     *
     * @param MethodParam[] $params
     */
    public function __construct(
        string $name,
        ?string $type = null,
        array $params = [],
        bool $isStatic = false,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        array $aliases = []
    ) {
        parent::__construct('method', $name, $type, $description, $class, $member, $aliases);
        $this->Params = $this->filterParams($params, $aliases);
        $this->IsStatic = $isStatic;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * Get the method's parameters, indexed by name
     *
     * @return array<string,MethodParam>
     */
    public function getParams(): array
    {
        return $this->Params;
    }

    /**
     * Check if the method is static
     */
    public function isStatic(): bool
    {
        return $this->IsStatic;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag} ";
        if ($this->IsStatic) {
            $string .= 'static ';
        }
        if (isset($this->Type)) {
            $string .= "{$this->Type} ";
        }
        $string .= "{$this->Name}(";
        $string .= Arr::implode(', ', $this->Params, '');
        $string .= ')';
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }

    /**
     * @param MethodParam[] $params
     * @param array<string,class-string> $aliases
     * @return array<string,MethodParam>
     */
    final protected function filterParams(array $params, array $aliases = []): array
    {
        foreach ($params as $param) {
            $name = $this->filterString($param->getName(), 'parameter name');
            $filtered[$name] = $param->withType($this->filterType($param->getType(), $aliases));
        }

        return $filtered ?? [];
    }
}
