<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;
use Salient\Tests\TestCase;

abstract class ReturnTypeExtensionTestCase extends TypeInferenceTestCase
{
    public function testAssertions(): void
    {
        $_file = TestCase::getFixturesPath(static::class) . 'Assertions.php';
        foreach (self::gatherAssertTypes($_file) as $args) {
            $assertType = array_shift($args);
            $file = array_shift($args);
            /** @var string $assertType */
            /** @var string $file */
            $this->assertFileAsserts($assertType, $file, ...$args);
        }
    }

    /**
     * @inheritDoc
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [TestCase::getPackagePath() . '/phpstan.extension.neon'];
    }
}
