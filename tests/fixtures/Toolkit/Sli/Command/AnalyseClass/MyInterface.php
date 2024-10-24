<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

use Stringable;

/**
 * Summary of MyInterface
 *
 * @api
 */
interface MyInterface
{
    public const MY_SHORT_STRING = 'short';

    /**
     * Summary of MyInterface::MY_ARRAY
     *
     * @var array<class-string,int>
     */
    public const MY_ARRAY = [
        Stringable::class => 0,
    ];

    /**
     * Summary of MyInterface::MyMethod()
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
