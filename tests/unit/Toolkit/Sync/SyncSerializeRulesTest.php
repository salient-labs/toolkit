<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Container\Container;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Sync\SyncSerializeRules;
use Salient\Tests\Sync\CustomEntity\Post as CustomPost;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\TestCase;
use Closure;

/**
 * @covers \Salient\Sync\SyncSerializeRules
 * @covers \Salient\Sync\SyncSerializeRulesBuilder
 */
final class SyncSerializeRulesTest extends TestCase
{
    public function testApply(): void
    {
        $container = new Container();

        $rules1 = SyncSerializeRules::build($container)
            ->entity(User::class)
            ->remove([
                'l1.l2.field1',
                ['l1.l2.field3', 'field3_id_1'],
                Post::class => [['e1_field1', 'e1_field1_id']],
                User::class => ['e3_field', ['e4_field', 'e4_field_id_1']],
            ])
            ->build();
        $rules2 = SyncSerializeRules::build($container)
            ->entity(User::class)
            ->remove([
                ['l1.l2.field1', 'field1_id_2'],
                'l1.l2.field2',
                ['l1.l2.field2', 'field2_id_2'],
                Post::class => [['e1_field2', 'e1_field2_id'], 'e1_field2'],
                CustomPost::class => ['e2_field2', ['e2_field2', 'e2_field2_id_2']],
                User::class => ['e4_field', ['e3_field', 'e3_field_id_2']],
            ])
            ->build();
        $rules3 = SyncSerializeRules::build($container)
            ->entity(User::class)
            ->remove([
                'l1.l2.field2',
                ['l1.l2.field3', 'field3_id_3'],
                CustomPost::class => ['e2_field3', ['e2_field2', 'e2_field2_id_3']],
                User::class => ['e3_field', ['e4_field', 'e4_field_id_3']],
            ])
            ->build();

        $this->assertEquals([
            [
                [User::class, 'l1.l2.field1', 'field1_id_2', null],
                [User::class, 'l1.l2.field3', 'field3_id_1', null],
                [User::class, 'l1.l2.field2', 'field2_id_2', null],
            ],
            Post::class => [[User::class, 'e1_field1', 'e1_field1_id', null], [User::class, 'e1_field2', null, null]],
            CustomPost::class => [[User::class, 'e2_field2', 'e2_field2_id_2', null]],
            User::class => [[User::class, 'e3_field', 'e3_field_id_2', null], [User::class, 'e4_field', null, null]],
        ], $this->getRemove($rules1->merge($rules2)));
        $this->assertEquals([
            [
                [User::class, 'l1.l2.field1', null, null],
                [User::class, 'l1.l2.field2', 'field2_id_2', null],
                [User::class, 'l1.l2.field3', 'field3_id_1', null],
            ],
            Post::class => [[User::class, 'e1_field2', null, null], [User::class, 'e1_field1', 'e1_field1_id', null]],
            CustomPost::class => [[User::class, 'e2_field2', 'e2_field2_id_2', null]],
            User::class => [[User::class, 'e4_field', 'e4_field_id_1', null], [User::class, 'e3_field', null, null]],
        ], $this->getRemove($rules2->merge($rules1)));
        $this->assertEquals([
            [
                [User::class, 'l1.l2.field2', 'field2_id_2', null],
                [User::class, 'l1.l2.field3', 'field3_id_1', null],
                [User::class, 'l1.l2.field1', null, null],
            ],
            CustomPost::class => [[User::class, 'e2_field3', null, null], [User::class, 'e2_field2', 'e2_field2_id_2', null]],
            User::class => [[User::class, 'e3_field', null, null], [User::class, 'e4_field', 'e4_field_id_1', null]],
            Post::class => [[User::class, 'e1_field2', null, null], [User::class, 'e1_field1', 'e1_field1_id', null]],
        ], $this->getRemove($rules3->merge($rules2)->merge($rules1)));
    }

    /**
     * @template T of SyncEntityInterface
     *
     * @param SyncSerializeRules<T> $rules
     * @return array<0|string,array<array{class-string,string,int|string|null,(Closure(mixed, SyncStoreInterface|null, SyncSerializeRules<T>): mixed)|null}>>
     */
    private function getRemove(SyncSerializeRules $rules): array
    {
        return (function () {
            /** @var SyncSerializeRules<T> $this */
            // @phpstan-ignore method.private
            return $this->getRemove();
        })->bindTo($rules, $rules)();
    }
}
