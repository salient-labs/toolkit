<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityResolverInterface;
use Salient\Sync\Support\SyncEntityFuzzyResolver as FuzzyResolver;
use Salient\Sync\Support\SyncEntityResolver as Resolver;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\SyncTestCase;
use InvalidArgumentException;

/**
 * @covers \Salient\Sync\Support\SyncEntityResolver
 * @covers \Salient\Sync\Support\SyncEntityFuzzyResolver
 */
final class SyncEntityResolverTest extends SyncTestCase
{
    /**
     * @dataProvider getByNameProvider
     *
     * @template T of SyncEntityInterface
     *
     * @param array<array{string,float}|array{null,null}>|string $expected
     * @param class-string<SyncEntityResolverInterface<T>> $resolver
     * @param class-string<T> $entity
     * @param mixed[] $args
     * @param string[] $names
     */
    public function testGetByName(
        $expected,
        string $resolver,
        string $entity,
        string $propertyName,
        array $args,
        array $names
    ): void {
        $provider = $entity::withDefaultProvider($this->App);
        $this->maybeExpectException($expected);
        /** @var SyncEntityResolverInterface<T> */
        $resolver = new $resolver($provider, $propertyName, ...$args);
        foreach ($names as $name) {
            $uncertainty = -1;
            $result = $resolver->getByName($name, $uncertainty);
            if ($result) {
                $result = $result->{$propertyName};
            }
            $actual[] = [$result, $uncertainty];
        }
        $this->assertSame($expected, $actual ?? []);
    }

    /**
     * @return array<array{array<array{string,float}|array{null,null}>|string,class-string<SyncEntityResolverInterface<SyncEntityInterface>>,class-string<SyncEntityInterface>,string,mixed[],string[]}>
     */
    public static function getByNameProvider(): array
    {
        $names = [
            'Leanne Graham',
            'leanne graham',
            'GRAHAM, leanne',
            'Lee-Anna Graham',
            'leanne graham',
            'GRAHAM, leanne',
            'Lee-Anna Graham',
        ];

        return [
            'first exact' => [
                [
                    ['Leanne Graham', 0.0],
                    [null, null],
                    [null, null],
                    [null, null],
                    [null, null],
                    [null, null],
                    [null, null],
                ],
                Resolver::class,
                User::class,
                'Name',
                [],
                $names,
            ],
            'levenshtein + normalise' => [
                [
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.0],
                    [null, null],
                    ['Leanne Graham', 0.2],
                    ['Leanne Graham', 0.0],
                    [null, null],
                    ['Leanne Graham', 0.2],
                ],
                FuzzyResolver::class,
                User::class,
                'Name',
                [FuzzyResolver::ALGORITHM_LEVENSHTEIN | FuzzyResolver::NORMALISE, 0.6],
                $names,
            ],
            'similar_text + normalise' => [
                [
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.5384615384615384],
                    ['Leanne Graham', 0.19999999999999996],
                    ['Leanne Graham', 0.0],
                    ['Leanne Graham', 0.5384615384615384],
                    ['Leanne Graham', 0.19999999999999996],
                ],
                FuzzyResolver::class,
                User::class,
                'Name',
                [FuzzyResolver::ALGORITHM_SIMILAR_TEXT | FuzzyResolver::NORMALISE, 0.6],
                $names,
            ],
            [
                InvalidArgumentException::class . ',At least one algorithm flag must be set',
                FuzzyResolver::class,
                User::class,
                'Name',
                [0],
                [],
            ],
            [
                InvalidArgumentException::class . ',Invalid $uncertaintyThreshold for ALGORITHM_NGRAM_SIMILARITY when $requireOneMatch is true',
                FuzzyResolver::class,
                User::class,
                'Name',
                [FuzzyResolver::DEFAULT_FLAGS, null, null, true],
                [],
            ],
        ];
    }
}
