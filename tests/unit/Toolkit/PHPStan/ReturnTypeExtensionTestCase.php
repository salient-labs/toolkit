<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan;

use PHPStan\Testing\TypeInferenceTestCase;
use Salient\Tests\TestCase;

abstract class ReturnTypeExtensionTestCase extends TypeInferenceTestCase
{
    use PHPStanTestCaseTrait;

    /**
     * @runInSeparateProcess
     */
    public function testAssertions(): void
    {
        foreach (static::getAssertionsFiles() as $_file) {
            foreach (self::gatherAssertTypes($_file) as $args) {
                /** @var string */
                $assertType = array_shift($args);
                /** @var string */
                $file = array_shift($args);
                $this->assertFileAsserts($assertType, $file, ...$args);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [TestCase::getPackagePath() . '/src/Toolkit/PHPStan/phpstan.extension.neon'];
    }

    /**
     * @return string[]
     */
    abstract protected static function getAssertionsFiles(): array;
}
