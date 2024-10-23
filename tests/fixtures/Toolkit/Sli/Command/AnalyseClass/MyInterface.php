<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

use Stringable;

/**
 * MyInterface
 *
 * @api
 */
interface MyInterface
{
    public const MY_SHORT_STRING = 'short';

    /**
     * MyInterface::MY_ARRAY
     *
     * @var array<class-string,int>
     */
    public const MY_ARRAY = [
        Stringable::class => 0,
    ];

    /**
     * MyInterface::MyMethod()
     *
     * @return mixed
     */
    public function MyMethod();

    /**
     * MyInterface::MyStaticMethod()
     *
     * @param static $instance
     */
    public static function MyStaticMethod(self $instance): void;
}
