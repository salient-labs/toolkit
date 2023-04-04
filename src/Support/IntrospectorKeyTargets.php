<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;

/**
 * How to create or update an instance from an array
 *
 * @property-read array<string,int> $Parameters Key => constructor parameter index
 * @property-read array<string,true> $PassByRefParameters Key => `true`
 * @property-read array<string,string> $Methods Key => "magic" property method
 * @property-read array<string,string> $Properties Key => declared property name
 * @property-read string[] $MetaProperties Arbitrary keys
 * @property-read string[] $DateProperties Date keys
 *
 * @see Introspector
 */
final class IntrospectorKeyTargets implements IReadable
{
    use TFullyReadable;

    /**
     * Key => constructor parameter index
     *
     * @var array<string,int>
     */
    protected $Parameters;

    /**
     * Key => `true`
     *
     * @var array<string,true>
     */
    protected $PassByRefParameters;

    /**
     * Key => "magic" property method
     *
     * @var array<string,string>
     */
    protected $Methods;

    /**
     * Key => declared property name
     *
     * @var array<string,string>
     */
    protected $Properties;

    /**
     * Arbitrary keys
     *
     * @var string[]
     */
    protected $MetaProperties;

    /**
     * Date keys
     *
     * @var string[]
     */
    protected $DateProperties;

    public function __construct(
        array $parameters,
        array $passByRefProperties,
        array $methods,
        array $properties,
        array $metaProperties,
        array $dateProperties
    ) {
        $this->Parameters = $parameters;
        $this->PassByRefParameters = $passByRefProperties;
        $this->Methods = $methods;
        $this->Properties = $properties;
        $this->MetaProperties = $metaProperties;
        $this->DateProperties = $dateProperties;
    }
}
