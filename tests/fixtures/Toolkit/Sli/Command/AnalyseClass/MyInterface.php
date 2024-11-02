<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

use Stringable;

/**
 * Summary of MyInterface
 *
 * @property-read int $MyMagicInterfaceProperty
 *
 * @method mixed MyMagicInterfaceMethod()
 *
 * @api
 */
interface MyInterface
{
    public const MY_SHORT_STRING = 'short';

    /**
     * Summary of MyInterface::MY_ARRAY
     */
    public const MY_ARRAY = [
        Stringable::class => 0,
    ];

    /**
     * Summary of MyInterface::MyMethod()
     *
     * Extended description of `MyInterface::MyMethod()`.
     *
     * @return mixed
     */
    public function MyMethod();

    /**
     * Summary of MyInterface::MyStaticMethod()
     *
     * @param static $instance
     */
    public static function MyStaticMethod(self $instance): void;
}
