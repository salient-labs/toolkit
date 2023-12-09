<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\Utility\Debug\GetCallerClass;

use function Lkrms\Tests\Utility\Debug\getCallerViaFunction;
use function Lkrms\Tests\Utility\Debug\getFunctionCallback;

final class DebugTest extends \Lkrms\Tests\TestCase
{
    public function testGetCaller(): void
    {
        $class = new GetCallerClass();

        $thisMethod = [
            'namespace' => 'Lkrms\\Tests\\Utility\\',
            'class' => 'DebugTest',
            0 => '->',
            'function' => __FUNCTION__,
            1 => ':',
        ];

        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '::',
            'function' => 'getCallerViaStaticMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, $class::getCallerViaStaticMethod());
        $this->assertIsCaller($thisMethod, $class::getCallerViaStaticMethod(1));

        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '->',
            'function' => 'getCallerViaMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, $class->getCallerViaMethod());
        $this->assertIsCaller($thisMethod, $class->getCallerViaMethod(1));

        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '->',
            'function' => 'getCallerViaMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class->getCallback())());
        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '->',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class->getCallback())(1));
        $this->assertIsCaller($thisMethod, ($class->getCallback())(2));

        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '::',
            'function' => 'getCallerViaStaticMethod',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class::getStaticCallback())());
        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'class' => 'GetCallerClass',
            0 => '::',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, ($class::getStaticCallback())(1));
        $this->assertIsCaller($thisMethod, ($class::getStaticCallback())(2));

        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'function' => 'getCallerViaFunction',
            1 => ':',
        ];
        $this->assertIsCaller($expected, getCallerViaFunction());
        $this->assertIsCaller($thisMethod, getCallerViaFunction(1));
        $this->assertIsCaller($expected, (getFunctionCallback())());
        $expected = [
            'namespace' => 'Lkrms\\Tests\\Utility\\Debug\\',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, (getFunctionCallback())(1));
        $this->assertIsCaller($thisMethod, (getFunctionCallback())(2));

        $expectedPath = $this->directorySeparatorToNative(
            $this->getFixturesPath(__CLASS__) . '/GetCallerFile2.php'
        );
        $expected = [
            'file' => $expectedPath,
            0 => '::',
            'function' => 'Lkrms_Tests_Runtime_getCallerViaFunction',
            1 => ':',
        ];
        $this->assertIsCaller($expected, Lkrms_Tests_Runtime_getCallerViaFunction());
        $this->assertIsCaller($thisMethod, Lkrms_Tests_Runtime_getCallerViaFunction(1));
        $this->assertIsCaller($expected, (Lkrms_Tests_Runtime_getFunctionCallback())());
        $expected = [
            'file' => $expectedPath,
            0 => '::',
            'function' => '{closure}',
            1 => ':',
        ];
        $this->assertIsCaller($expected, (Lkrms_Tests_Runtime_getFunctionCallback())(1));
        $this->assertIsCaller($thisMethod, (Lkrms_Tests_Runtime_getFunctionCallback())(2));
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
