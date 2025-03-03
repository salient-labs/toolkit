<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Utility;

use Salient\Tests\PHPStan\ReturnTypeExtensionTestCase;

/**
 * @covers \Salient\PHPStan\Utility\ArrExtendReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\ArrFlattenReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\ArrWhereNotEmptyReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\ArrWhereNotNullReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\GetCoalesceReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\StrCoalesceReturnTypeExtension
 * @covers \Salient\PHPStan\Internal\ArgType
 */
final class UtilityReturnTypeExtensionTest extends ReturnTypeExtensionTestCase
{
    /**
     * @inheritDoc
     */
    protected static function getAssertionsFiles(): array
    {
        return [
            __DIR__ . '/data/ArrExtendReturnTypeExtensionAssertions.php',
            __DIR__ . '/data/ArrFlattenReturnTypeExtensionAssertions.php',
            __DIR__ . '/data/ArrWhereNotEmptyReturnTypeExtensionAssertions.php',
            __DIR__ . '/data/ArrWhereNotNullReturnTypeExtensionAssertions.php',
            __DIR__ . '/data/GetCoalesceReturnTypeExtensionAssertions.php',
            __DIR__ . '/data/StrCoalesceReturnTypeExtensionAssertions.php',
        ];
    }
}
