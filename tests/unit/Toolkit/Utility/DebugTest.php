<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Core\Utility\Debug;
use Salient\Tests\Core\Utility\Debug\GetCallerClass;
use Salient\Tests\TestCase;

use function Salient\Tests\Core\Utility\Debug\getCallerViaFunction;
use function Salient\Tests\Core\Utility\Debug\getFunctionCallback;

/**
 * @covers \Salient\Core\Utility\Debug
 */
final class DebugTest extends TestCase
{
    public function testGetCaller(): void
    {
        $class = new GetCallerClass();

        $thisMethod = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\',
            'class' => 'DebugTest',
            0 => '->',
            'function' => __FUNCTION__,
            1 => ':',
        ];

        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '::',
            'function' => 'getCallerViaStaticMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, $class::getCallerViaStaticMethod());
        $this->assertIsCaller($thisMethod, $class::getCallerViaStaticMethod(1));

        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '->',
            'function' => 'getCallerViaMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, $class->getCallerViaMethod());
        $this->assertIsCaller($thisMethod, $class->getCallerViaMethod(1));

        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '->',
            'function' => 'getCallerViaMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class->getCallback())());
        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '->',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class->getCallback())(1));
        $this->assertIsCaller($thisMethod, ($class->getCallback())(2));

        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '::',
            'function' => 'getCallerViaStaticMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class::getStaticCallback())());
        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '::',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class::getStaticCallback())(1));
        $this->assertIsCaller($thisMethod, ($class::getStaticCallback())(2));

        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'function' => 'getCallerViaFunction',
            1 => ':',
        ];
        $this->assertIsCaller($expected, getCallerViaFunction());
        $this->assertIsCaller($thisMethod, getCallerViaFunction(1));
        $this->assertIsCaller($expected, (getFunctionCallback())());
        $expected = [
            'namespace' => 'Salient\\Tests\\Core\\Utility\\Debug\\',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, (getFunctionCallback())(1));
        $this->assertIsCaller($thisMethod, (getFunctionCallback())(2));

        $expectedPath = $this->directorySeparatorToNative(
            self::getFixturesPath(__CLASS__) . '/GetCallerFile2.php'
        );
        $expected = [
            'file' => $expectedPath,
            0 => '::',
            'function' => 'Salient_Tests_Core_Utility_Debug_getCallerViaFunction',
            1 => ':',
        ];
        $this->assertIsCaller($expected, Salient_Tests_Core_Utility_Debug_getCallerViaFunction());
        $this->assertIsCaller($thisMethod, Salient_Tests_Core_Utility_Debug_getCallerViaFunction(1));
        $this->assertIsCaller($expected, (Salient_Tests_Core_Utility_Debug_getFunctionCallback())());
        $expected = [
            'file' => $expectedPath,
            0 => '::',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, (Salient_Tests_Core_Utility_Debug_getFunctionCallback())(1));
        $this->assertIsCaller($thisMethod, (Salient_Tests_Core_Utility_Debug_getFunctionCallback())(2));

        /** @var non-empty-array<array{file:string,...}> */
        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $last = end($backtrace);
        $expected = [
            'file' => $last['file'],
            0 => ':',
        ];
        $this->assertIsCaller($expected, Debug::getCaller(($depth = count($backtrace)) - 1));
        $this->assertSame([], Debug::getCaller($depth));
    }

    /**
     * @param mixed[] $expected
     * @param mixed[] $actual
     */
    private function assertIsCaller(array $expected, array $actual): void
    {
        $this->assertSame('line', array_key_last($actual));
        $this->assertIsInt(array_pop($actual));
        $this->assertSame($expected, $actual);
    }
}
