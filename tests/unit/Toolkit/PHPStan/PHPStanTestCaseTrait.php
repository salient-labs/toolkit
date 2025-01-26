<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan;

use PHPStan\Testing\PHPStanTestCase;

/**
 * @phpstan-require-extends PHPStanTestCase
 */
trait PHPStanTestCaseTrait
{
    protected function setUp(): void
    {
        PHPStanTestCase::getContainer();

        parent::setUp();
    }
}
