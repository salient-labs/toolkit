<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\Introspector;
use Lkrms\Tests\Support\Introspector\A;
use Lkrms\Tests\Support\Introspector\B;

final class IntrospectorTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider getProvider
     *
     * @param class-string $class
     * @param array<string,mixed> $expected
     */
    public function testGet(array $expected, string $class): void
    {
        $introspector = Introspector::get($class);
        foreach ($expected as $property => $value) {
            $this->assertSame($value, $introspector->$property, "Introspector::\${$property}");
        }
    }

    /**
     * @return array<string,array{array<string,mixed>,string}>
     */
    public static function getProvider(): array
    {
        return [
            A::class => [
                [
                    'Class' => A::class,
                    'IsReadable' => true,
                    'IsWritable' => true,
                    'IsExtensible' => true,
                    'IsProvidable' => false,
                    'IsRelatable' => false,
                    'IsTreeable' => false,
                    'HasDates' => false,
                    'Properties' => [
                        'id' => 'Id',
                        'name' => 'Name',
                        'not_writable' => 'NotWritable',
                    ],
                    'PublicProperties' => [],
                    'ReadableProperties' => [
                        'id' => 'Id',
                        'name' => 'Name',
                        'not_writable' => 'NotWritable',
                    ],
                    'WritableProperties' => [
                        'id' => 'Id',
                        'name' => 'Name',
                    ],
                    'Actions' => [],
                    'Parameters' => [],
                    'RequiredParameters' => [],
                    'ServiceParameters' => [],
                    'PassByRefParameters' => [],
                    'DateParameters' => [],
                    'DefaultArguments' => [],
                    'ParameterIndex' => [],
                    'SerializableProperties' => [
                        'Id',
                        'Name',
                    ],
                    'NormalisedKeys' => [
                        'id',
                        'name',
                        'not_writable',
                    ],
                    'ParentProperty' => null,
                    'ChildrenProperty' => null,
                    'OneToOneRelationships' => [],
                    'OneToManyRelationships' => [],
                    'DateKeys' => [],
                ],
                A::class,
            ],
            B::class => [
                [
                    'Class' => B::class,
                    'IsReadable' => true,
                    'IsWritable' => true,
                    'IsExtensible' => true,
                    'IsProvidable' => false,
                    'IsRelatable' => false,
                    'IsTreeable' => false,
                    'HasDates' => false,
                    'Properties' => [
                        'id' => 'Id',
                        'name' => 'Name',
                        'not_writable' => 'NotWritable',
                        'created_at' => 'CreatedAt',
                        'modified_at' => 'ModifiedAt',
                    ],
                    'PublicProperties' => [],
                    'ReadableProperties' => [
                        'id' => 'Id',
                        'name' => 'Name',
                        'not_writable' => 'NotWritable',
                        'created_at' => 'CreatedAt',
                        'modified_at' => 'ModifiedAt',
                    ],
                    'WritableProperties' => [
                        'id' => 'Id',
                        'name' => 'Name',
                    ],
                    'Actions' => [
                        'get' => [
                            'data' => '_getData',
                            'meta' => '_getMeta',
                        ],
                        'set' => [
                            'data' => '_setData',
                            'meta' => '_setMeta',
                        ],
                        'isset' => [
                            'meta' => '_issetMeta',
                        ],
                        'unset' => [
                            'meta' => '_unsetMeta',
                        ],
                    ],
                    'Parameters' => [
                        'created_at' => 'createdAt',
                    ],
                    'RequiredParameters' => [
                        'created_at' => 'createdAt',
                    ],
                    'ServiceParameters' => [
                        'created_at' => 'DateTimeImmutable',
                    ],
                    'PassByRefParameters' => [],
                    'DateParameters' => [],
                    'DefaultArguments' => [
                        null,
                    ],
                    'ParameterIndex' => [
                        'createdAt' => 0,
                    ],
                    'SerializableProperties' => [
                        'Id',
                        'Name',
                        'data',
                        'meta',
                    ],
                    'NormalisedKeys' => [
                        'id',
                        'name',
                        'not_writable',
                        'created_at',
                        'modified_at',
                        'data',
                        'meta',
                    ],
                    'ParentProperty' => null,
                    'ChildrenProperty' => null,
                    'OneToOneRelationships' => [],
                    'OneToManyRelationships' => [],
                    'DateKeys' => [],
                ],
                B::class,
            ],
        ];
    }
}
