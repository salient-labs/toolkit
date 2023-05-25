<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

/**
 * MyInterface
 *
 * @api
 */
interface MyInterface
{
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
