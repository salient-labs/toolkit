<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Concept\FluentDelegate;
use Lkrms\Concern\HasMutator;
use Lkrms\Contract\IImmutable;
use Lkrms\Support\FluentDelegator;

final class FluentDelegatorTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider mutableDelegateProvider
     *
     * @param (callable(FluentDelegatorTestMutableDelegate): bool)|bool $condition1
     * @param (callable(FluentDelegatorTestMutableDelegate): bool)|bool $condition2
     */
    public function testMutableDelegate(string $expected, FluentDelegatorTestMutableDelegate $delegate, $condition1, $condition2)
    {
        $delegate2 =
            $delegate->if($condition1)
                     ->withProperty('a')
                     ->elseIf($condition2)
                     ->withProperty('b')
                     ->else()
                     ->withProperty('c')
                     ->endIf();
        $this->assertSame($delegate, $delegate2);
        $this->assertSame($expected, $delegate2->Property);
    }

    public static function mutableDelegateProvider()
    {
        return [
            ['a', new FluentDelegatorTestMutableDelegate(), true, false],
            ['a', new FluentDelegatorTestMutableDelegate(), true, true],
            ['c', new FluentDelegatorTestMutableDelegate(), false, false],
            ['b', new FluentDelegatorTestMutableDelegate(), false, true],
            ['a', new FluentDelegatorTestMutableDelegate(), fn() => true, fn() => false],
            ['a', new FluentDelegatorTestMutableDelegate(), fn() => true, fn() => true],
            ['c', new FluentDelegatorTestMutableDelegate(), fn() => false, fn() => false],
            ['b', new FluentDelegatorTestMutableDelegate(), fn() => false, fn() => true],
        ];
    }

    /**
     * @dataProvider immutableDelegateProvider
     *
     * @param (callable(FluentDelegatorTestImmutableDelegate): bool)|bool $condition1
     * @param (callable(FluentDelegatorTestImmutableDelegate): bool)|bool $condition2
     */
    public function testImmutableDelegate(string $expected, FluentDelegatorTestImmutableDelegate $delegate, $condition1, $condition2)
    {
        $delegate2 =
            $delegate->if($condition1)
                     ->withProperty('a')
                     ->elseIf($condition2)
                     ->withProperty('b')
                     ->else()
                     ->withProperty('c')
                     ->endIf();
        $this->assertNotSame($delegate, $delegate2);
        $this->assertNotEquals($delegate, $delegate2);
        $this->assertSame($expected, $delegate2->Property);
    }

    public static function immutableDelegateProvider()
    {
        return [
            ['a', new FluentDelegatorTestImmutableDelegate(), true, false],
            ['a', new FluentDelegatorTestImmutableDelegate(), true, true],
            ['c', new FluentDelegatorTestImmutableDelegate(), false, false],
            ['b', new FluentDelegatorTestImmutableDelegate(), false, true],
            ['a', new FluentDelegatorTestImmutableDelegate(), fn() => true, fn() => false],
            ['a', new FluentDelegatorTestImmutableDelegate(), fn() => true, fn() => true],
            ['c', new FluentDelegatorTestImmutableDelegate(), fn() => false, fn() => false],
            ['b', new FluentDelegatorTestImmutableDelegate(), fn() => false, fn() => true],
        ];
    }
}

class FluentDelegatorTestMutableDelegate extends FluentDelegate
{
    /**
     * @var string
     */
    public $Property;

    /**
     * @return $this|FluentDelegator<$this>
     */
    public function withProperty(string $value)
    {
        $this->Property = $value;
        return $this;
    }
}

class FluentDelegatorTestImmutableDelegate extends FluentDelegatorTestMutableDelegate implements IImmutable
{
    use HasMutator;

    public function withProperty(string $value)
    {
        return $this->withPropertyValue('Property', $value);
    }
}
