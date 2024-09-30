<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Core\ArrayMapperInterface;
use Salient\Contract\Core\ListConformity;
use Salient\Core\ArrayMapper;
use Salient\Tests\TestCase;
use InvalidArgumentException;
use Throwable;
use ValueError;

/**
 * @covers \Salient\Core\ArrayMapper
 */
final class ArrayMapperTest extends TestCase
{
    /**
     * @dataProvider mapProvider
     *
     * @param array<string,mixed>|class-string<Throwable>|null $expected
     * @param array<array-key,array-key|array-key[]> $keyMap
     * @param array<string,mixed> $in
     * @param ListConformity::* $conformity
     * @param int-mask-of<ArrayMapperInterface::*> $flags
     */
    public function testMap(
        $expected,
        array $keyMap,
        array $in,
        $conformity = ListConformity::NONE,
        int $flags = ArrayMapperInterface::ADD_UNMAPPED
    ): void {
        $mapper = new ArrayMapper($keyMap, $conformity, $flags);

        if (is_array($expected)) {
            $this->assertSame($expected, $mapper->map($in));
            return;
        }

        $this->expectException(
            $expected !== null
                ? $expected
                : (\PHP_VERSION_ID < 80000
                    ? InvalidArgumentException::class
                    : ValueError::class)
        );
        $mapper->map($in);
    }

    /**
     * @return array<string,array{array<string,mixed>|class-string<Throwable>|null,array<array-key,array-key|array-key[]>,array<string,mixed>,3?:ListConformity::*,4?:int-mask-of<ArrayMapperInterface::*>}>
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
                ListConformity::NONE,
                ArrayMapperInterface::ADD_UNMAPPED | ArrayMapperInterface::ADD_MISSING,
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
                ListConformity::NONE,
                ArrayMapperInterface::ADD_UNMAPPED,
            ],
            'add unmapped + ignore missing + remove null' => [
                [
                    'aa_mapped' => 'value 1',
                    'dd_dangerous' => 'value 7',
                    'F_unmapped' => 'value 3',
                ],
                $map,
                $in,
                ListConformity::NONE,
                ArrayMapperInterface::ADD_UNMAPPED | ArrayMapperInterface::REMOVE_NULL,
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
                ListConformity::NONE,
                ArrayMapperInterface::ADD_MISSING,
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
                ListConformity::NONE,
                0,
            ],
            'ignore unmapped + ignore missing + remove null' => [
                [
                    'aa_mapped' => 'value 1',
                    'dd_dangerous' => 'value 7',
                ],
                $map,
                $in,
                ListConformity::NONE,
                ArrayMapperInterface::REMOVE_NULL,
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
                ListConformity::NONE,
                ArrayMapperInterface::REQUIRE_MAPPED,
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
                ListConformity::NONE,
                ArrayMapperInterface::REQUIRE_MAPPED | ArrayMapperInterface::REMOVE_NULL,
            ],
            'require mapped + missing input key' => [
                InvalidArgumentException::class,
                $map2,
                [
                    'USER_ID' => 32,
                    'FULL_NAME' => 'Greta',
                ],
                ListConformity::NONE,
                ArrayMapperInterface::REQUIRE_MAPPED,
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
                ListConformity::COMPLETE,
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
                ListConformity::COMPLETE,
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
                ListConformity::COMPLETE,
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
                ListConformity::COMPLETE,
                ArrayMapperInterface::ADD_UNMAPPED | ArrayMapperInterface::REMOVE_NULL,
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
                ListConformity::COMPLETE,
                0,
            ],
        ];
    }
}
