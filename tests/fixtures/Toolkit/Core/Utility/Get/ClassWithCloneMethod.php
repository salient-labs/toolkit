<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

use stdClass;

class ClassWithCloneMethod
{
    public static int $Instances = 0;

    public object $Foo;

    public function __construct()
    {
        self::$Instances++;
        $this->Foo = new stdClass();
    }

    public function __clone()
    {
        self::$Instances++;
    }
}
