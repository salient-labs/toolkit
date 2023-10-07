<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Composer\InstalledVersions;
use Lkrms\Facade\Composer;

final class ComposerTest extends \Lkrms\Tests\TestCase
{
    public function testHasDevDependencies(): void
    {
        $this->assertTrue(Composer::hasDevDependencies());
    }

    public function testGetRootPackageName(): void
    {
        $this->assertSame('lkrms/util', Composer::getRootPackageName());
    }

    public function testGetRootPackageReference(): void
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetRootPackageVersion(): void
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetRootPackagePath(): void
    {
        $this->assertSame(realpath(dirname(__DIR__, 3)), Composer::getRootPackagePath());
    }

    public function testGetPackageReference(): void
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetPackageVersion(): void
    {
        $this->assertSame(
            InstalledVersions::getPrettyVersion('phpstan/phpstan'),
            Composer::getPackageVersion('phpstan/phpstan', true)
        );
        $this->assertSame(
            InstalledVersions::getPrettyVersion('phpstan/phpstan')
                . '-' . substr(InstalledVersions::getReference('phpstan/phpstan'), 0, 8),
            Composer::getPackageVersion('phpstan/phpstan', true, true)
        );
        $this->assertNull(Composer::getPackageVersion('composer/composer'));
    }

    public function testGetPackagePath(): void
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetClassPath(): void
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetNamespacePath(): void
    {
        $this->expectNotToPerformAssertions();
    }
}
