<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\Introspector;
use Lkrms\Tests\Support\Introspector\A;
use Lkrms\Tests\Support\Introspector\B;
use Lkrms\Tests\Support\Introspector\C;
use Lkrms\Tests\Support\Introspector\FirstAndLastNamesA;
use Lkrms\Tests\Support\Introspector\FirstAndLastNamesB;
use Lkrms\Tests\Support\Introspector\FirstNameAndSurnameA;
use Lkrms\Tests\Support\Introspector\FirstNameAndSurnameB;
use Lkrms\Tests\Support\Introspector\FirstNameOnlyA;
use Lkrms\Tests\Support\Introspector\FirstNameOnlyB;
use Lkrms\Tests\Support\Introspector\LastNameOnlyA;
use Lkrms\Tests\Support\Introspector\LastNameOnlyB;
use Lkrms\Tests\Support\Introspector\SurnameOnlyA;
use Lkrms\Tests\Support\Introspector\SurnameOnlyB;
use Salient\Tests\TestCase;
use Generator;

final class IntrospectorTest extends TestCase
{
    /**
     * @dataProvider getProvider
     *
     * @param array<string,mixed> $expected
     * @param class-string $class
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
                    'NotNullableParameters' => [],
                    'ServiceParameters' => [],
                    'PassByRefParameters' => [],
                    'DateParameters' => [],
                    'DefaultArguments' => [],
                    'RequiredArguments' => 0,
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
                    'NotNullableParameters' => [],
                    'ServiceParameters' => [
                        'created_at' => 'DateTimeImmutable',
                    ],
                    'PassByRefParameters' => [],
                    'DateParameters' => [],
                    'DefaultArguments' => [
                        null,
                    ],
                    'RequiredArguments' => 1,
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
            C::class => [
                [
                    'Class' => C::class,
                    'IsReadable' => false,
                    'IsWritable' => false,
                    'IsExtensible' => false,
                    'IsProvidable' => false,
                    'IsRelatable' => false,
                    'IsTreeable' => false,
                    'HasDates' => false,
                    'Properties' => [],
                    'PublicProperties' => [],
                    'ReadableProperties' => [],
                    'WritableProperties' => [],
                    'Actions' => [],
                    'Parameters' => [
                        'long' => 'long',
                        'short' => 'short',
                        'valueName' => 'valueName',
                        'type' => 'type',
                        'valueType' => 'valueType',
                        'description' => 'description',
                    ],
                    'RequiredParameters' => [
                        'long' => 'long',
                    ],
                    'NotNullableParameters' => [
                        'type' => 'type',
                        'valueType' => 'valueType',
                    ],
                    'ServiceParameters' => [],
                    'PassByRefParameters' => [],
                    'DateParameters' => [],
                    'DefaultArguments' => [
                        null,
                        null,
                        null,
                        0,
                        0,
                        null,
                    ],
                    'RequiredArguments' => 3,
                    'ParameterIndex' => [
                        'long' => 0,
                        'short' => 1,
                        'valueName' => 2,
                        'type' => 3,
                        'valueType' => 4,
                        'description' => 5,
                    ],
                    'SerializableProperties' => [],
                    'NormalisedKeys' => [],
                    'ParentProperty' => null,
                    'ChildrenProperty' => null,
                    'OneToOneRelationships' => [],
                    'OneToManyRelationships' => [],
                    'DateKeys' => [],
                ],
                C::class,
            ]
        ];
    }

    /**
     * @dataProvider getGetNameClosureProvider
     *
     * @param array<string,string> $normalisations
     * @param class-string $class
     */
    public function testGetGetNameClosure(
        string $expected,
        array $normalisations,
        string $class
    ): void {
        $introspector = Introspector::get($class);
        $getNameClosure = $introspector->getGetNameClosure();
        $this->assertSame(
            array_values($normalisations),
            $introspector->maybeNormalise(array_keys($normalisations))
        );
        $this->assertSame($expected, $getNameClosure(new $class()));
    }

    /**
     * @return Generator<array{string,array<string,string>,class-string}>
     */
    public static function getGetNameClosureProvider(): Generator
    {
        $classes = [
            [
                FirstAndLastNamesA::class,
                FirstNameAndSurnameA::class,
                FirstNameOnlyA::class,
                LastNameOnlyA::class,
                SurnameOnlyA::class,
            ],
            [
                FirstAndLastNamesB::class,
                FirstNameAndSurnameB::class,
                FirstNameOnlyB::class,
                LastNameOnlyB::class,
                SurnameOnlyB::class,
            ],
        ];

        $normalisations = [
            [
                'Surname' => 'surname',
                'Last name' => 'last_name',
                'First name' => 'first_name',
            ],
            [
                'Surname' => 'SURNAME',
                'Last name' => 'LAST-NAME',
                'First name' => 'FIRST-NAME',
            ],
        ];

        $names = [
            [
                'Plutarch Heavensbee',
                'Plutarch Heavensbee',
                '<FirstNameOnlyA>',
                'Head Gamemaker',
                '#71',
            ],
            [
                'Plutarch Heavensbee',
                'Plutarch Heavensbee',
                '<FirstNameOnlyB>',
                'Head Gamemaker',
                '#71',
            ],
        ];

        foreach ($classes as $i => $classes) {
            foreach ($classes as $j => $class) {
                yield [
                    $names[$i][$j],
                    $normalisations[$i],
                    $class,
                ];
            }
        }
    }
}
