<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Support\Catalog\TextComparisonAlgorithm as Algorithm;
use Lkrms\Support\Catalog\TextComparisonFlag as Flag;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Sync\Support\SyncEntityFuzzyResolver;
use Lkrms\Sync\Support\SyncEntityResolver;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;

final class SyncEntityResolverTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    /**
     * @dataProvider getByNameProvider
     *
     * @template T of ISyncEntity
     *
     * @param array<array{string|null,float|null}> $expected
     * @param class-string<ISyncEntityResolver<T>> $resolver
     * @param class-string<T> $entity
     * @param mixed[] $args
     * @param string[] $names
     */
    public function testGetByName(
        array $expected,
        string $resolver,
        string $entity,
        string $propertyName,
        array $args,
        array $names
    ): void {
        /** @var ISyncEntityProvider<T> */
        $provider = [$entity, 'withDefaultProvider']($this->App);
        /** @var ISyncEntityResolver<T> */
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
     * @return array<string,array{array<array{string|null,float|null}>,class-string<ISyncEntityResolver<ISyncEntity>>,class-string<ISyncEntity>,string,mixed[],string[]}>
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
                SyncEntityResolver::class,
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
                SyncEntityFuzzyResolver::class,
                User::class,
                'Name',
                [Algorithm::LEVENSHTEIN | Flag::NORMALISE, 0.6],
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
                SyncEntityFuzzyResolver::class,
                User::class,
                'Name',
                [Algorithm::SIMILAR_TEXT | Flag::NORMALISE, 0.6],
                $names,
            ]
        ];
    }
}
