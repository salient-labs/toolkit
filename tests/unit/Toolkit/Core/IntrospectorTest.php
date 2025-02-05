<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\Introspector;
use Salient\Tests\Core\Introspector\A;
use Salient\Tests\Core\Introspector\B;
use Salient\Tests\Core\Introspector\C;
use Salient\Tests\Core\Introspector\FirstAndLastNamesA;
use Salient\Tests\Core\Introspector\FirstAndLastNamesB;
use Salient\Tests\Core\Introspector\FirstNameAndSurnameA;
use Salient\Tests\Core\Introspector\FirstNameAndSurnameB;
use Salient\Tests\Core\Introspector\FirstNameOnlyA;
use Salient\Tests\Core\Introspector\FirstNameOnlyB;
use Salient\Tests\Core\Introspector\LastNameOnlyA;
use Salient\Tests\Core\Introspector\LastNameOnlyB;
use Salient\Tests\Core\Introspector\SurnameOnlyA;
use Salient\Tests\Core\Introspector\SurnameOnlyB;
use Salient\Tests\Core\Introspector\X;
use Salient\Tests\Core\Introspector\Y;
use Salient\Tests\TestCase;
use Generator;

/**
 * @covers \Salient\Core\Introspector
 */
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
                            'secret' => '_setSecret',
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
                        'created_at' => 'DateTimeInterface',
                    ],
                    'PassByRefParameters' => [],
                    'DateParameters' => [
                        'created_at' => 'createdAt',
                    ],
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
                        'secret',
                    ],
                    'ParentProperty' => null,
                    'ChildrenProperty' => null,
                    'OneToOneRelationships' => [],
                    'OneToManyRelationships' => [],
                    'DateKeys' => [
                        3 => 'created_at',
                        4 => 'modified_at',
                    ],
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
                    'HasDates' => true,
                    'Properties' => [
                        'Long' => 'Long',
                        'Short' => 'Short',
                    ],
                    'PublicProperties' => [
                        'Long' => 'Long',
                        'Short' => 'Short',
                    ],
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
                    'PassByRefParameters' => [
                        'description' => 'description',
                    ],
                    'DateParameters' => [],
                    'DefaultArguments' => [
                        null,
                        null,
                        null,
                        1,
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
                    'SerializableProperties' => [
                        'Long',
                        'Short',
                    ],
                    'NormalisedKeys' => [
                        'Long',
                        'Short',
                    ],
                    'ParentProperty' => null,
                    'ChildrenProperty' => null,
                    'OneToOneRelationships' => [],
                    'OneToManyRelationships' => [],
                    'DateKeys' => [
                        'Long',
                        'Short',
                    ],
                ],
                C::class,
            ],
            X::class => [
                [
                    'Class' => X::class,
                    'IsReadable' => true,
                    'IsWritable' => true,
                    'IsExtensible' => false,
                    'IsProvidable' => false,
                    'IsRelatable' => true,
                    'IsTreeable' => false,
                    'HasDates' => false,
                    'Properties' => [
                        'my-int' => 'MyInt',
                        'my-y' => 'MyY',
                    ],
                    'PublicProperties' => [],
                    'ReadableProperties' => [
                        'my-int' => 'MyInt',
                        'my-y' => 'MyY',
                    ],
                    'WritableProperties' => [],
                    'Actions' => [
                        'set' => [
                            'my-y' => '_setMyY',
                        ],
                        'unset' => [
                            'my-y' => '_unsetMyY',
                        ],
                    ],
                    'Parameters' => [],
                    'RequiredParameters' => [],
                    'NotNullableParameters' => [],
                    'ServiceParameters' => [],
                    'PassByRefParameters' => [],
                    'DateParameters' => [],
                    'DefaultArguments' => [],
                    'RequiredArguments' => 0,
                    'ParameterIndex' => [],
                    'SerializableProperties' => [],
                    'NormalisedKeys' => [
                        'my-int',
                        'my-y',
                    ],
                    'ParentProperty' => null,
                    'ChildrenProperty' => null,
                    'OneToOneRelationships' => [
                        'my-y' => Y::class,
                    ],
                    'OneToManyRelationships' => [],
                    'DateKeys' => [],
                ],
                X::class,
            ],
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
