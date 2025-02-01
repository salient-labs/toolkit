<?php declare(strict_types=1);

namespace Salient\Core\Reflection;

/**
 * @api
 */
final class ParameterIndex
{
    /**
     * An array that maps normalised names to declared names for parameters
     *
     * @var array<string,string>
     */
    public array $Names;

    /**
     * An array that maps declared names to positions (0-based) for parameters
     *
     * @var array<string,int>
     */
    public array $Positions;

    /**
     * A list of default values for non-variadic parameters
     *
     * For parameters where no default value is available, `null` is applied.
     *
     * @var list<mixed>
     */
    public array $DefaultArguments;

    /**
     * An array that maps normalised names to declared names for parameters that
     * do not accept null values
     *
     * @var array<string,string>
     */
    public array $NotNullable;

    /**
     * An array that maps normalised names to declared names for parameters that
     * are not optional and do not accept null values
     *
     * @var array<string,string>
     */
    public array $Required;

    /**
     * An array that maps normalised names to declared names for parameters that
     * are passed by reference
     *
     * @var array<string,string>
     */
    public array $PassedByReference;

    /**
     * An array that maps normalised names to declared names for date parameters
     *
     * @var array<string,string>
     */
    public array $Date;

    /**
     * An array that maps normalised names to types for parameters with a
     * built-in type
     *
     * @var array<string,string>
     */
    public array $BuiltinTypes;

    /**
     * An array that maps normalised names to class names for parameters that
     * accept a service
     *
     * @var array<string,class-string>
     */
    public array $Services;

    /**
     * The minimum number of arguments that must be given
     */
    public int $RequiredArgumentCount;

    /**
     * @internal
     *
     * @param array<string,string> $names
     * @param array<string,int> $positions
     * @param list<mixed> $defaultArguments
     * @param array<string,string> $notNullable
     * @param array<string,string> $required
     * @param array<string,string> $passedByReference
     * @param array<string,string> $date
     * @param array<string,string> $builtinTypes
     * @param array<string,class-string> $services
     */
    public function __construct(
        array $names,
        array $positions,
        array $defaultArguments,
        array $notNullable,
        array $required,
        array $passedByReference,
        array $date,
        array $builtinTypes,
        array $services,
        int $requiredArgumentCount
    ) {
        $this->Names = $names;
        $this->Positions = $positions;
        $this->DefaultArguments = $defaultArguments;
        $this->NotNullable = $notNullable;
        $this->Required = $required;
        $this->PassedByReference = $passedByReference;
        $this->Date = $date;
        $this->BuiltinTypes = $builtinTypes;
        $this->Services = $services;
        $this->RequiredArgumentCount = $requiredArgumentCount;
    }
}
