<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Utility\Type;

use Salient\Tests\PHPStan\ReturnTypeExtensionTestCase;

/**
 * @covers \Salient\PHPStan\Utility\Type\ArrExtendReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\Type\ArrFlattenReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\Type\ArrWhereNotEmptyReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\Type\ArrWhereNotNullReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\Type\GetCoalesceReturnTypeExtension
 * @covers \Salient\PHPStan\Utility\Type\StrCoalesceReturnTypeExtension
 */
final class UtilityReturnTypeExtensionTest extends ReturnTypeExtensionTestCase
{
    /**
     * @inheritDoc
     */
    protected static function getAssertionsFiles(): array
    {
        return [
            __DIR__ . '/ArrExtendReturnTypeExtensionAssertions.php',
            __DIR__ . '/ArrFlattenReturnTypeExtensionAssertions.php',
            __DIR__ . '/ArrWhereNotEmptyReturnTypeExtensionAssertions.php',
            __DIR__ . '/ArrWhereNotNullReturnTypeExtensionAssertions.php',
            __DIR__ . '/GetCoalesceReturnTypeExtensionAssertions.php',
            __DIR__ . '/StrCoalesceReturnTypeExtensionAssertions.php',
        ];
    }
}
