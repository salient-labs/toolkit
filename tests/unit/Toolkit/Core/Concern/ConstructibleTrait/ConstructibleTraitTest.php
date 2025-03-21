<?php declare(strict_types=1);

namespace Salient\Tests\Core\Concern\ConstructibleTrait;

use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Constructible;
use Salient\Contract\Core\Entity\Extensible;
use Salient\Contract\Core\Entity\Normalisable;
use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Core\Entity\Writable;
use Salient\Core\Concern\ConstructibleTrait;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\NormalisableTrait;
use Salient\Core\Concern\ReadableTrait;
use Salient\Core\Concern\WritableTrait;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use LogicException;

/**
 * @covers \Salient\Core\Concern\ConstructibleTrait
 */
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
     * @param Constructible::* $conformity
     * @param (Treeable&T)|null $parent
     */
    public function testConstruct(
        $expected,
        string $class,
        array $data,
        int $conformity = Constructible::CONFORMITY_NONE,
        ?ContainerInterface $container = null,
        $parent = null
    ): void {
        $this->maybeExpectException($expected);

        if (Arr::isList($data)) {
            /** @var list<mixed[]> $data */
            $this->assertEquals(
                $expected,
                Get::array($class::constructMultiple(
                    $data,
                    $conformity,
                    $parent,
                    $container ?? new Container(),
                )),
            );
            return;
        }

        $this->assertEquals(
            $expected,
            $class::construct($data, $parent, $container ?? new Container()),
        );
    }

    /**
     * @return array<array{Constructible[]|Constructible|string,class-string<Constructible>,mixed[],3?:Constructible::*,4?:ContainerInterface|null,5?:Treeable|null}>
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
            [[$a, $a2], A::class, [$mixedCase, $mixedCase2], Constructible::CONFORMITY_COMPLETE],
            [$exA, A::class, [$snakeCase, $snakeCase2]],
            [$exB, B::class, [$snakeCase, $snakeCase2]],
            [[$c, $c2], C::class, [$snakeCase, $snakeCase2]],
            [[$c, $c2], C::class, [$snakeCase, $snakeCase2], Constructible::CONFORMITY_COMPLETE],
            [$exC, C::class, [$withData, $withData2]],
            [[$d, $d2], D::class, [$withData, $withData2]],
            [[$d, $d2], D::class, [$withData, $withData2], Constructible::CONFORMITY_COMPLETE],
        ];
    }
}

class A implements Constructible
{
    use ConstructibleTrait;

    public int $Id;
    public string $Name;
    /** @var array-key */
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
class B implements Constructible, Readable, Writable, Normalisable
{
    use ConstructibleTrait;
    use ReadableTrait;
    use WritableTrait;
    use NormalisableTrait;

    protected int $Id;
    protected string $Name;
    /** @var array-key */
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
