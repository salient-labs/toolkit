<?php

declare(strict_types=1);

namespace Lkrms\Ioc;

use Dice\Dice;
use RuntimeException;
use UnexpectedValueException;

/**
 * Dependency injection (DI) / Inversion of control (IoC)
 *
 * Just a facade for `Dice`.
 *
 * @package Lkrms
 * @link https://r.je/dice Dice home page
 * @link https://github.com/Level-2/Dice Dice repository on GitHub
 */
abstract class Ioc
{
    /**
     * @var Dice
     */
    private static $Container;

    /**
     * @var Dice[]
     */
    private static $ContainerStack = [];

    private static $DefaultRule = [
        "inherit" => false,
    ];

    private static function checkContainer()
    {
        if (!self::$Container)
        {
            self::$Container = new Dice();
        }
    }

    /**
     * Push a copy of the active DI Container onto the stack
     */
    public static function push()
    {
        self::checkContainer();
        self::$ContainerStack[] = clone self::$Container;
    }

    /**
     * Pop the most recently pushed DI Container off the stack and activate it
     *
     * @throws RuntimeException if the DI Container stack is empty
     */
    public static function pop()
    {
        if (is_null($container = array_pop(self::$ContainerStack)))
        {
            throw new RuntimeException("DI Container stack is empty");
        }

        self::$Container = $container;
    }

    private static function checkRule(array $rule)
    {
        /**
         * @todo Improve consistency of results by resolving 'shareInstances',
         * 'substitutions', named instances and rule keys recursively
         */
        if (!empty($subs = array_intersect(
            $rule['shareInstances'] ?? [],
            array_keys($rule["substitutions"] ?? [])
        )))
        {
            throw new UnexpectedValueException("Dependencies in 'shareInstances' cannot be substituted: " . implode(", ", $subs));
        }
    }

    private static function addRule(string $name, array $rule)
    {
        self::checkContainer();
        $container = self::$Container->addRule($name, $rule);
        self::checkRule($container->getRule($name));
        self::$Container = $container;
    }

    /**
     * Returns an instance of a class with its dependencies resolved
     *
     * @param string $name The name of the class to instantiate.
     * @param array|null $params Parameters to pass to the constructor. Ignored
     * if `$name` is a shared dependency and has already been instantiated.
     * @return mixed
     */
    public static function create(string $name, array $params = null)
    {
        self::checkContainer();

        return self::$Container->create($name, $params ?: []);
    }

    /**
     * Returns the name of the class instantiated for the given dependency
     *
     * @param string $name
     * @return string
     */
    public static function resolve(string $name): string
    {
        self::checkContainer();

        /**
         * @todo Suppress instantiation and dependency resolution
         */
        return get_class(self::$Container->create($name));
    }

    /**
     * Resolve requests for a dependency with instances of the given class
     *
     * When the DI Container needs an instance of `$name`, create a new
     * `$instanceOf`, passing any `$constructParams` to its constructor and only
     * creating one instance of any classes named in `$shareInstances`.
     *
     * @param string $name
     * @param string|null $instanceOf Default: `$name`
     * @param array|null $constructParams
     * @param array|null $shareInstances
     * @param array $customRule Dice rules may be specified here.
     */
    public static function register(
        string $name,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    )
    {
        $rule = array_merge(self::$DefaultRule, $customRule);

        if (!is_null($instanceOf))
        {
            $rule["instanceOf"] = $instanceOf;
        }

        if (!is_null($constructParams))
        {
            $rule["constructParams"] = $constructParams;
        }

        if (!is_null($shareInstances))
        {
            $rule["shareInstances"] = array_merge($rule["shareInstances"] ?? [], $shareInstances);
        }

        self::addRule($name, $rule);
    }

    /**
     * Resolve requests for a dependency with a shared instance of the given
     * class
     *
     * When the DI Container needs an instance of `$name`, use a previously
     * created instance if possible, otherwise create a new `$instanceOf` as per
     * {@see Ioc::register()}, and store it for use with other requests for
     * `$name`.
     *
     * @param string $name
     * @param string|null $instanceOf Default: `$name`
     * @param array|null $constructParams
     * @param array|null $shareInstances
     * @param array $customRule Dice rules may be specified here.
     */
    public static function registerSingleton(
        string $name,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    )
    {
        $customRule["shared"] = true;

        self::register(
            $name,
            $instanceOf,
            $constructParams,
            $shareInstances,
            $customRule
        );
    }

    /**
     * Replace the given class's requests for one dependency with another
     *
     * When the DI Container is resolving `$forName`'s dependencies, replace
     * requests for `$name` with requests for `$instanceOf`.
     *
     * @param string $forName
     * @param string $name
     * @param string $instanceOf
     */
    public static function registerFor(string $forName, string $name, string $instanceOf)
    {
        self::addRule($forName, [
            "substitutions" => [$name => $instanceOf],
        ]);
    }
}

