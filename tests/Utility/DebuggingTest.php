<?php

declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\Utility\Debugging\GetCallerClass;
use function Lkrms\Tests\Utility\Debugging\getCallerViaFunction;
use function Lkrms\Tests\Utility\Debugging\getFunctionCallback;

final class DebuggingTest extends \Lkrms\Tests\TestCase
{
    public function testGetCaller()
    {
        $class = new GetCallerClass();

        $thisMethod = [
            "class"    => static::class,
            0          => "->",
            "function" => __FUNCTION__,
            1          => ":",
        ];

        $expected = [
            "class"    => GetCallerClass::class,
            0          => "::",
            "function" => "getCallerViaStaticMethod",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], $class::getCallerViaStaticMethod());
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], $class::getCallerViaStaticMethod(1));

        $expected = [
            "class"    => GetCallerClass::class,
            0          => "->",
            "function" => "getCallerViaMethod",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], $class->getCallerViaMethod());
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], $class->getCallerViaMethod(1));

        $expected = [
            "class"    => GetCallerClass::class,
            0          => "->",
            "function" => "getCallerViaMethod",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], ($class->getCallback())());
        $expected = [
            "class"    => GetCallerClass::class,
            0          => "->",
            "function" => "{closure}",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], ($class->getCallback())(1));
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], ($class->getCallback())(2));

        $expected = [
            "class"    => GetCallerClass::class,
            0          => "::",
            "function" => "getCallerViaStaticMethod",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], ($class::getStaticCallback())());
        $expected = [
            "class"    => GetCallerClass::class,
            0          => "::",
            "function" => "{closure}",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], ($class::getStaticCallback())(1));
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], ($class::getStaticCallback())(2));

        $expected = [
            "namespace" => "Lkrms\\Tests\\Utility\\Debugging\\",
            "function"  => "getCallerViaFunction",
            1           => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], getCallerViaFunction());
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], getCallerViaFunction(1));
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], (getFunctionCallback())());
        $expected = [
            "namespace" => "Lkrms\\Tests\\Utility\\Debugging\\",
            "function"  => "{closure}",
            1           => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], (getFunctionCallback())(1));
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], (getFunctionCallback())(2));

        $expected = [
            "file"     => __DIR__ . "/Debugging/GetCallerFile.php",
            0          => "::",
            "function" => "Lkrms_Tests_Runtime_getCallerViaFunction",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], Lkrms_Tests_Runtime_getCallerViaFunction());
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], Lkrms_Tests_Runtime_getCallerViaFunction(1));
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], (Lkrms_Tests_Runtime_getFunctionCallback())());
        $expected = [
            "file"     => __DIR__ . "/Debugging/GetCallerFile.php",
            0          => "::",
            "function" => "{closure}",
            1          => ":",
        ];
        $this->assertArrayHasSubArrayAndKeys($expected, ["line"], (Lkrms_Tests_Runtime_getFunctionCallback())(1));
        $this->assertArrayHasSubArrayAndKeys($thisMethod, ["line"], (Lkrms_Tests_Runtime_getFunctionCallback())(2));
    }
}

require (__DIR__ . "/Debugging/GetCallerFile.php");
