<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Core\Exception\InvalidDataException;
use Salient\Core\ArrayMapper;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\ArrayMapper
 */
final class ArrayMapperTest extends TestCase
{
    /**
     * @dataProvider mapProvider
     *
     * @param array<string,mixed>|string $expected
     * @param array<array-key,array-key|array-key[]> $keyMap
     * @param array<string,mixed> $in
     * @param ArrayMapper::CONFORMITY_* $conformity
     * @param int-mask-of<ArrayMapper::REMOVE_NULL|ArrayMapper::ADD_UNMAPPED|ArrayMapper::ADD_MISSING|ArrayMapper::REQUIRE_MAPPED> $flags
     */
    public function testMap(
        $expected,
        array $keyMap,
        array $in,
        int $conformity = ArrayMapper::CONFORMITY_NONE,
        int $flags = ArrayMapper::ADD_UNMAPPED
    ): void {
        $mapper = new ArrayMapper($keyMap, $conformity, $flags);
        $this->maybeExpectException($expected);
        $this->assertSame($expected, $mapper->map($in));
    }

    /**
     * @return array<string,array{array<string,mixed>|string,array<array-key,array-key|array-key[]>,array<string,mixed>,3?:ArrayMapper::CONFORMITY_*,4?:int-mask-of<ArrayMapper::REMOVE_NULL|ArrayMapper::ADD_UNMAPPED|ArrayMapper::ADD_MISSING|ArrayMapper::REQUIRE_MAPPED>}>
     */
    public static function mapProvider(): array
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
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::ADD_UNMAPPED | ArrayMapper::ADD_MISSING,
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
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::ADD_UNMAPPED,
            ],
            'add unmapped + ignore missing + remove null' => [
                [
                    'aa_mapped' => 'value 1',
                    'dd_dangerous' => 'value 7',
                    'F_unmapped' => 'value 3',
                ],
                $map,
                $in,
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::ADD_UNMAPPED | ArrayMapper::REMOVE_NULL,
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
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::ADD_MISSING,
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
                ArrayMapper::CONFORMITY_NONE,
                0,
            ],
            'ignore unmapped + ignore missing + remove null' => [
                [
                    'aa_mapped' => 'value 1',
                    'dd_dangerous' => 'value 7',
                ],
                $map,
                $in,
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::REMOVE_NULL,
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
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::REQUIRE_MAPPED,
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
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::REQUIRE_MAPPED | ArrayMapper::REMOVE_NULL,
            ],
            'require mapped + missing input key' => [
                InvalidDataException::class . ',Input key not found: MAIL',
                $map2,
                [
                    'USER_ID' => 32,
                    'FULL_NAME' => 'Greta',
                ],
                ArrayMapper::CONFORMITY_NONE,
                ArrayMapper::REQUIRE_MAPPED | ArrayMapper::ADD_MISSING,
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
                ArrayMapper::CONFORMITY_COMPLETE,
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
                ArrayMapper::CONFORMITY_COMPLETE,
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
                ArrayMapper::CONFORMITY_COMPLETE,
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
                ArrayMapper::CONFORMITY_COMPLETE,
                ArrayMapper::ADD_UNMAPPED | ArrayMapper::REMOVE_NULL,
            ],
            'complete conformity + extra input key' => [
                InvalidDataException::class . ',Invalid input array',
                $map2,
                [
                    'USER_ID' => 38,
                    'FULL_NAME' => 'Amir',
                    'MAIL' => 'amir@domain.test',
                    'URI' => 'https://domain.test/~amir',
                ],
                ArrayMapper::CONFORMITY_COMPLETE,
                0,
            ],
        ];
    }
}
