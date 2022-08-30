<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TReadable;
use Lkrms\Contract\IReadable;
use UnexpectedValueException;

/**
 * Represents a SQL query
 *
 * @property-read array<string,mixed> $Values Parameter name => value
 */
class SqlQuery implements IReadable
{
    use TReadable;

    public const AND = "AND";
    public const OR  = "OR";

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
     * @internal
     * @var array<string,mixed>
     */
    protected $Values = [];

    /**
     * @var callable
     */
    protected $ParamCallback;

    public static function getReadable(): array
    {
        return ["Values"];
    }

    /**
     * @param $paramCallback Applied to the name of each parameter added to the
     * query.
     * ```php
     * function (string $name): string
     * ```
     */
    public function __construct(callable $paramCallback)
    {
        $this->ParamCallback = $paramCallback;
    }

    /**
     * Add a parameter and return a query placeholder for it
     *
     */
    public function addParam(string $name, $value): string
    {
        if (array_key_exists($name, $this->Values))
        {
            throw new UnexpectedValueException("Parameter already added: $name");
        }
        $_name = ($this->ParamCallback)($name);
        $this->Values[$name] = $value;
        return $_name;
    }

}
