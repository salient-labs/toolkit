<?php declare(strict_types=1);

namespace Salient\Tests\Core\Concern\ConstructibleTrait;

use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Constructible;
use Salient\Contract\Core\Extensible;
use Salient\Contract\Core\Normalisable;
use Salient\Contract\Core\NormaliserFactory;
use Salient\Contract\Core\Readable;
use Salient\Contract\Core\Treeable;
use Salient\Contract\Core\Writable;
use Salient\Core\Catalog\ListConformity;
use Salient\Core\Concern\ConstructibleTrait;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\HasNormaliser;
use Salient\Core\Concern\HasReadableProperties;
use Salient\Core\Concern\HasWritableProperties;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Tests\TestCase;
use LogicException;

final class ConstructibleTraitTest extends TestCase
{
    /**
     * @dataProvider constructProvider
     *
     * @template T of Constructible
     *
     * @param T[]|T|string $expected
     * @param class-string<T> $class
     * @param mixed[] $data
     * @param ListConformity::*|null $conformity
     * @param (Treeable&T)|null $parent
     */
    public function testConstruct(
        $expected,
        string $class,
        array $data,
        $conformity = ListConformity::NONE,
        ?ContainerInterface $container = null,
        $parent = null
    ): void {
        $this->maybeExpectException($expected);

        if (Arr::isList($data)) {
            $this->assertEquals(
                $expected,
                Get::array($class::constructList(
                    $data,
                    $conformity,
                    $container ?? new Container(),
                    $parent,
                )),
            );
            return;
        }

        $this->assertEquals(
            $expected,
            $class::construct($data, $container ?? new Container(), $parent),
        );
    }

    /**
     * @return array<array{Constructible[]|Constructible|string,class-string<Constructible>,mixed[],3?:ListConformity::*|null,4?:ContainerInterface|null,5?:Treeable|null}>
     */
    public static function constructProvider(): array
    {
        $mixedCase = [
            'id' => 1,
            'name' => 'foo',
            'OtherId' => 'bar',
        ];
        $mixedCase2 = ['id' => 2] + $mixedCase;

        $snakeCase = [
            'id' => 1,
            'name' => 'foo',
            'other_id' => 'bar',
        ];
        $snakeCase2 = ['id' => 2] + $snakeCase;

        $withData = $snakeCase + [
            'DATA' => [11, 13, 17],
        ];
        $withData2 = ['id' => 2] + $withData;

        $a = new A(1, 'foo');
        $a->OtherId = 'bar';
        $a2 = new A(2, 'foo');
        $a2->OtherId = 'bar';

        $c = new C(1, 'foo');
        $c->OtherId = 'bar';
        $c2 = new C(2, 'foo');
        $c2->OtherId = 'bar';

        $d = new D(1, 'foo');
        $d->OtherId = 'bar';
        $d->DATA = [11, 13, 17];
        $d2 = new D(2, 'foo');
        $d2->OtherId = 'bar';
        $d2->DATA = [11, 13, 17];

        $exA = LogicException::class . ',Cannot apply other_id to ' . A::class;
        $exB = LogicException::class . ',Cannot set unwritable property: ' . B::class . '::$Id';
        $exC = LogicException::class . ',Cannot apply DATA to ' . C::class;

        return [
            [$a, A::class, $mixedCase],
            [$exA, A::class, $snakeCase],
            [$exB, B::class, $snakeCase],
            [$c, C::class, $snakeCase],
            [$exC, C::class, $withData],
            [$d, D::class, $withData],
            [[$a, $a2], A::class, [$mixedCase, $mixedCase2]],
            [[$a, $a2], A::class, [$mixedCase, $mixedCase2], ListConformity::COMPLETE],
            [$exA, A::class, [$snakeCase, $snakeCase2]],
            [$exB, B::class, [$snakeCase, $snakeCase2]],
            [[$c, $c2], C::class, [$snakeCase, $snakeCase2]],
            [[$c, $c2], C::class, [$snakeCase, $snakeCase2], ListConformity::COMPLETE],
            [$exC, C::class, [$withData, $withData2]],
            [[$d, $d2], D::class, [$withData, $withData2]],
            [[$d, $d2], D::class, [$withData, $withData2], ListConformity::COMPLETE],
        ];
    }
}

class A implements Constructible
{
    use ConstructibleTrait;

    public int $Id;

    public string $Name;

    /**
     * @var array-key
     */
    public $OtherId;

    public function __construct(int $id, string $name)
    {
        $this->Id = $id;
        $this->Name = $name;
    }
}

/**
 * @property int $Id
 * @property string $Name
 * @property array-key $OtherId
 */
class B implements Constructible, Readable, Writable, Normalisable, NormaliserFactory
{
    use ConstructibleTrait;
    use HasReadableProperties;
    use HasWritableProperties;
    use HasNormaliser;

    protected int $Id;

    protected string $Name;

    /**
     * @var array-key
     */
    protected $OtherId;

    public static function getReadableProperties(): array
    {
        return ['Id', 'Name', 'OtherId'];
    }

    public static function getWritableProperties(): array
    {
        return ['OtherId'];
    }
}

class C extends B
{
    public function __construct(int $id, string $name)
    {
        $this->Id = $id;
        $this->Name = $name;
    }
}

class D extends C implements Extensible
{
    use ExtensibleTrait;
}
