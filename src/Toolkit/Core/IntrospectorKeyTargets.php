<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Core\Concern\ReadableProtectedPropertiesTrait;
use Closure;

/**
 * @property-read array<string,int> $Parameters Key => constructor parameter index
 * @property-read array<string,true> $PassByRefParameters Key => `true`
 * @property-read array<string,true> $NotNullableParameters Key => `true`
 * @property-read array<Closure(mixed[], ?string, TClass, ?TProvider, ?TContext): void> $Callbacks Arbitrary callbacks
 * @property-read array<string,string> $Methods Key => "magic" property method
 * @property-read array<string,string> $Properties Key => declared property name
 * @property-read string[] $MetaProperties Arbitrary keys
 * @property-read string[] $DateProperties Date keys
 * @property-read array<TIntrospector::*_KEY,string> $CustomKeys Identifier => key
 * @property-read int $LastParameterIndex Index of the last constructor parameter to which array values are mapped
 *
 * @internal
 *
 * @template TIntrospector of Introspector
 * @template TClass of object
 * @template TProvider of ProviderInterface
 * @template TContext of ProviderContextInterface
 */
final class IntrospectorKeyTargets implements Readable
{
    use ReadableProtectedPropertiesTrait;

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
     * Key => `true`
     *
     * @var array<string,true>
     */
    protected $NotNullableParameters;

    /**
     * Arbitrary callbacks
     *
     * @var array<Closure(mixed[], ?string, TClass, ?TProvider, ?TContext): void>
     */
    protected $Callbacks;

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

    /**
     * Identifier => key
     *
     * @var array<TIntrospector::*_KEY,string>
     */
    protected $CustomKeys;

    /**
     * Index of the last constructor parameter to which array values are mapped
     *
     * @var int
     */
    protected $LastParameterIndex = -1;

    /**
     * @param array<string,int> $parameters
     * @param array<string,true> $passByRefParameters
     * @param array<string,true> $notNullableParameters
     * @param array<Closure(mixed[], ?string, TClass, ?TProvider, ?TContext): void> $callbacks
     * @param array<string,string> $methods
     * @param array<string,string> $properties
     * @param string[] $metaProperties
     * @param string[] $dateProperties
     * @param array<TIntrospector::*_KEY,string> $customKeys
     */
    public function __construct(
        array $parameters,
        array $passByRefParameters,
        array $notNullableParameters,
        array $callbacks,
        array $methods,
        array $properties,
        array $metaProperties,
        array $dateProperties,
        array $customKeys
    ) {
        $this->Parameters = $parameters;
        $this->PassByRefParameters = $passByRefParameters;
        $this->NotNullableParameters = $notNullableParameters;
        $this->Callbacks = $callbacks;
        $this->Methods = $methods;
        $this->Properties = $properties;
        $this->MetaProperties = $metaProperties;
        $this->DateProperties = $dateProperties;
        $this->CustomKeys = $customKeys;

        if ($parameters) {
            $this->LastParameterIndex = max($parameters);
        }
    }
}
