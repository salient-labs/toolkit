<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\FluentInterface;
use Lkrms\Concern\TReadable;
use Lkrms\Contract\IReadable;
use UnexpectedValueException;

/**
 * A simple representation of a SQL query
 *
 * @property-read array<string,mixed> $Values Parameter name => value
 */
final class SqlQuery extends FluentInterface implements IReadable
{
    use TReadable;

    public const AND = 'AND';
    public const OR = 'OR';

    /**
     * A list of optionally nested WHERE clauses
     *
     * To join a list of clauses with an explicit operator:
     *
     * ```php
     * [
     *     '__' => SqlQuery::AND,
     *     'Id = ?',
     *     'Deleted IS NULL',
     * ]
     * ```
     *
     * To use nested clauses:
     *
     * ```php
     * [
     *     '__' => SqlQuery::AND,
     *     'ItemKey = ?',
     *     [
     *         '__' => SqlQuery::OR,
     *         'Expiry IS NULL',
     *         'Expiry > ?',
     *     ],
     * ]
     * ```
     *
     * @var array<int|string,string|array>
     */
    public $Where = [];

    /**
     * Parameter name => value
     *
     * @var array<string,mixed>
     */
    protected $Values = [];

    /**
     * @var callable
     */
    protected $ParamCallback;

    public static function getReadable(): array
    {
        return ['Values'];
    }

    /**
     * @param callable $paramCallback Applied to the name of each parameter
     * added to the query.
     * ```php
     * function (string $name): string
     * ```
     */
    public function __construct(callable $paramCallback)
    {
        $this->ParamCallback = $paramCallback;
    }

    /**
     * Add a parameter and assign its query placeholder to a variable
     *
     * @return $this
     */
    public function addParam(string $name, $value, ?string &$placeholder)
    {
        if (array_key_exists($name, $this->Values)) {
            throw new UnexpectedValueException("Parameter already added: $name");
        }

        $placeholder = ($this->ParamCallback)($name);
        $this->Values[$name] = $value;

        return $this;
    }

    /**
     * Add a WHERE clause
     *
     * See {@see SqlQuery::$Where}.
     *
     * @param callable|string|array $clause A string, an array, or a callback
     * that returns a string or array.
     * ```php
     * fn(): string|array
     * ```
     * @return $this
     */
    public function where($clause)
    {
        $this->Where[] = is_callable($clause) ? $clause() : $clause;

        return $this;
    }

    /**
     * Add "<name> IN (<value>[,<value>])" to the query unless a list of values
     * is empty
     *
     * @return $this
     */
    public function whereValueInList(string $name, ...$value)
    {
        if (!count($value)) {
            return $this;
        }

        $expr = [];
        foreach ($value as $_value) {
            $expr[] = $this->addNextParam($_value);
        }
        $this->Where[] = "$name IN (" . implode(',', $expr) . ')';

        return $this;
    }

    public function getWhere(?array &$values = null): ?string
    {
        $values = $this->Values;

        return $this->buildWhere($this->Where) ?: null;
    }

    private function addNextParam($value): string
    {
        $this->addParam('param_' . count($this->Values), $value, $param);

        return $param;
    }

    private function buildWhere(array $where)
    {
        $glue = $where['__'] ?? self::AND;
        unset($where['__']);
        foreach ($where as $i => $clause) {
            if (is_array($clause)) {
                if (!($clause = $this->buildWhere($clause))) {
                    unset($where[$i]);
                    continue;
                }
                $where[$i] = "($clause)";
            }
        }

        return implode(" $glue ", $where);
    }
}
