<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Support\SerializeRulesBuilder;
use Lkrms\Tests\Sync\CustomEntity\Post as CustomPost;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;

final class SerializeRulesTest extends \Lkrms\Tests\TestCase
{
    public function testApply()
    {
        $rules1 = (SerializeRulesBuilder::build()
            ->doNotSerialize([
                Post::class => ['e1.field1'],
                User::class => ['e3.field'],
            ])
            ->go());
        $rules2 = (SerializeRulesBuilder::build()
            ->doNotSerialize([
                Post::class       => ['e1.field2'],
                CustomPost::class => ['e2.field2'],
                User::class       => ['e3.field'],
            ])
            ->idKeyCallback(fn($key) => $key . '_id')
            ->go());
        $rules3 = (SerializeRulesBuilder::build()
            ->doNotSerialize([
                CustomPost::class => ['e2.field3'],
                User::class       => ['e3.field'],
            ])
            ->idKeyCallback([$this, 'getKey'])
            ->go());

        $this->assertEquals([
            Post::class       => ['e1.field1', 'e1.field2'],
            CustomPost::class => ['e2.field2'],
            User::class       => ['e3.field', 'e3.field'],
        ], $rules1->apply($rules2)->DoNotSerialize);
        $this->assertEquals([
            Post::class       => ['e1.field2', 'e1.field1'],
            CustomPost::class => ['e2.field2'],
            User::class       => ['e3.field', 'e3.field'],
        ], $rules2->apply($rules1)->DoNotSerialize);
        $this->assertEquals([
            Post::class       => ['e1.field2', 'e1.field1'],
            CustomPost::class => ['e2.field3', 'e2.field2'],
            User::class       => ['e3.field', 'e3.field', 'e3.field'],
        ], $rules3->apply($rules2)->apply($rules1)->DoNotSerialize);

        $this->assertEquals('__field__', ($rules3->apply($rules2)->IdKeyCallback)('field'));
        $this->assertEquals('field_id', ($rules2->apply($rules3)->IdKeyCallback)('field'));
        $this->assertEquals('__field__', ($rules1->apply($rules3)->IdKeyCallback)('field'));
    }

    public function getKey($key)
    {
        return "__{$key}__";
    }

}
