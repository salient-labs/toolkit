<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Facade\Mapper;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use UnexpectedValueException;
use ValueError;

final class ArrayMapperTest extends \Lkrms\Tests\TestCase
{
    public function testGetKeyMapClosureCache()
    {
        $map = [
            'USER_ID' => 'Id',
            'FULL_NAME' => 'Name',
            'MAIL' => 'Email',
        ];
        $closure1 = Mapper::getKeyMapClosure($map, ArrayKeyConformity::NONE, ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::ADD_MISSING);
        $closure2 = Mapper::getKeyMapClosure($map, ArrayKeyConformity::NONE, ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::ADD_MISSING);
        $this->assertSame($closure1, $closure2);
    }

    /**
     * @dataProvider getKeyMapClosureProvider
     *
     * @param array<string,mixed>|class-string<\Throwable>|null $expected
     * @param array<array-key,array-key|array-key[]> $map
     * @param array<string,mixed> $in
     * @param ArrayKeyConformity::* $conformity
     */
    public function testGetKeyMapClosure($expected, array $map, array $in, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        $closure = Mapper::getKeyMapClosure($map, $conformity, $flags);
        if (!is_array($expected)) {
            $this->expectException(
                $expected
                    ?: (PHP_VERSION_ID < 80000
                        ? UnexpectedValueException::class
                        : ValueError::class)
            );
            $closure($in);
            return;
        }
        $this->assertSame($expected, $closure($in));
    }

    public static function getKeyMapClosureProvider()
    {
        $in = [
            'A_mapped' => 'value 1',
            'B_mapped_null' => null,
            'D_dangerous' => 'value 7',
            'E_dangerous_null' => null,
            'F_unmapped' => 'value 3',
            'G_unmapped_null' => null,
            'dd_dangerous' => null,
            'ee_dangerous_null' => 'value 5',
        ];

        $map = [
            'A_mapped' => 'aa_mapped',
            'B_mapped_null' => 'bb_mapped_null',
            'C_missing' => 'cc_missing',
            'D_dangerous' => 'dd_dangerous',
            'E_dangerous_null' => 'ee_dangerous_null',
        ];

        $map2 = [
            'USER_ID' => 'Id',
            'FULL_NAME' => 'Name',
            'MAIL' => 'Email',
        ];

        return [
            'add unmapped + add missing + keep null' => [
                [
                    'aa_mapped' => 'value 1',
                    'bb_mapped_null' => null,
                    'cc_missing' => null,
                    'dd_dangerous' => 'value 7',
                    'ee_dangerous_null' => null,
                    'F_unmapped' => 'value 3',
                    'G_unmapped_null' => null,
                ],
                $map,
                $in,
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::ADD_MISSING,
            ],
            'add unmapped + ignore missing + keep null' => [
                [
                    'aa_mapped' => 'value 1',
                    'bb_mapped_null' => null,
                    'dd_dangerous' => 'value 7',
                    'ee_dangerous_null' => null,
                    'F_unmapped' => 'value 3',
                    'G_unmapped_null' => null,
                ],
                $map,
                $in,
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::ADD_UNMAPPED,
            ],
            'add unmapped + ignore missing + remove null' => [
                [
                    'aa_mapped' => 'value 1',
                    'dd_dangerous' => 'value 7',
                    'F_unmapped' => 'value 3',
                ],
                $map,
                $in,
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::REMOVE_NULL,
            ],
            'ignore unmapped + add missing + keep null' => [
                [
                    'aa_mapped' => 'value 1',
                    'bb_mapped_null' => null,
                    'cc_missing' => null,
                    'dd_dangerous' => 'value 7',
                    'ee_dangerous_null' => null,
                ],
                $map,
                $in,
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::ADD_MISSING,
            ],
            'ignore unmapped + ignore missing + keep null' => [
                [
                    'aa_mapped' => 'value 1',
                    'bb_mapped_null' => null,
                    'dd_dangerous' => 'value 7',
                    'ee_dangerous_null' => null,
                ],
                $map,
                $in,
                ArrayKeyConformity::NONE,
                0,
            ],
            'ignore unmapped + ignore missing + remove null' => [
                [
                    'aa_mapped' => 'value 1',
                    'dd_dangerous' => 'value 7',
                ],
                $map,
                $in,
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::REMOVE_NULL,
            ],
            'ignore unmapped + require mapped + keep null' => [
                [
                    'Id' => 32,
                    'Name' => 'Greta',
                    'Email' => null,
                ],
                $map2,
                [
                    'USER_ID' => 32,
                    'FULL_NAME' => 'Greta',
                    'MAIL' => null,
                ],
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::REQUIRE_MAPPED,
            ],
            'ignore unmapped + require mapped + remove null' => [
                [
                    'Id' => 32,
                    'Name' => 'Greta',
                ],
                $map2,
                [
                    'USER_ID' => 32,
                    'FULL_NAME' => 'Greta',
                    'MAIL' => null,
                ],
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::REQUIRE_MAPPED | ArrayMapperFlag::REMOVE_NULL,
            ],
            'require mapped + missing input key' => [
                UnexpectedValueException::class,
                $map2,
                [
                    'USER_ID' => 32,
                    'FULL_NAME' => 'Greta',
                ],
                ArrayKeyConformity::NONE,
                ArrayMapperFlag::REQUIRE_MAPPED,
            ],
            'complete conformity + ignore unmapped + ignore missing + keep null #1' => [
                [
                    'Id' => 32,
                    'Name' => 'Greta',
                    'Email' => 'greta@domain.test',
                ],
                $map2,
                [
                    'USER_ID' => 32,
                    'FULL_NAME' => 'Greta',
                    'MAIL' => 'greta@domain.test',
                ],
                ArrayKeyConformity::COMPLETE,
                0,
            ],
            'complete conformity + ignore unmapped + ignore missing + keep null #2' => [
                [
                    'Id' => 71,
                    'Name' => 'Terry',
                    'Email' => null,
                ],
                $map2,
                [
                    'USER_ID' => 71,
                    'FULL_NAME' => 'Terry',
                    'MAIL' => null,
                ],
                ArrayKeyConformity::COMPLETE,
                0,
            ],
            'complete conformity + field order changed + ignore unmapped + ignore missing + keep null' => [
                // Not desirable, but not unexpected
                [
                    'Id' => 'Terry',
                    'Name' => 71,
                    'Email' => null,
                ],
                $map2,
                [
                    'FULL_NAME' => 'Terry',
                    'USER_ID' => 71,
                    'MAIL' => null,
                ],
                ArrayKeyConformity::COMPLETE,
                0,
            ],
            'complete conformity + add unmapped + ignore missing + remove null' => [
                [
                    'Id' => 71,
                    'Name' => 'Terry',
                ],
                $map2,
                [
                    'USER_ID' => 71,
                    'FULL_NAME' => 'Terry',
                    'MAIL' => null,
                ],
                ArrayKeyConformity::COMPLETE,
                ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::REMOVE_NULL,
            ],
            'complete conformity + extra input key' => [
                null,
                $map2,
                [
                    'USER_ID' => 38,
                    'FULL_NAME' => 'Amir',
                    'MAIL' => 'amir@domain.test',
                    'URI' => 'https://domain.test/~amir',
                ],
                ArrayKeyConformity::COMPLETE,
                0,
            ],
        ];
    }
}
