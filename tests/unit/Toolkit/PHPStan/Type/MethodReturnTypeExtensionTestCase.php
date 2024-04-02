<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;
use Salient\Tests\TestCase;

abstract class MethodReturnTypeExtensionTestCase extends TypeInferenceTestCase
{
    /**
     * @dataProvider assertionsProvider
     *
     * @param mixed ...$args
     */
    public function testAssertions(string $assertType, string $file, ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /**
     * @return iterable<mixed>
     */
    public static function assertionsProvider(): iterable
    {
        yield from self::gatherAssertTypes(TestCase::getFixturesPath(static::class) . 'Assertions.php');
    }

    /**
     * @inheritDoc
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [TestCase::getPackagePath() . '/phpstan.extension.neon'];
    }
}
