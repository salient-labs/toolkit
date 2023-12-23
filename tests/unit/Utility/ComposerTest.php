<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Composer\InstalledVersions;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\Package;

final class ComposerTest extends TestCase
{
    public function testHasDevDependencies(): void
    {
        $this->assertTrue(Package::hasDevPackages());
    }

    public function testGetRootPackageName(): void
    {
        $this->assertSame('lkrms/util', Package::name());
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
        $this->assertSame(realpath(dirname(__DIR__, 3)), Package::path());
    }

    public function testGetPackageReference(): void
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetPackageVersion(): void
    {
        $this->assertSame(
            InstalledVersions::getPrettyVersion('phpstan/phpstan'),
            Package::packageVersion('phpstan/phpstan')
        );
        $this->assertSame(
            InstalledVersions::getPrettyVersion('phpstan/phpstan')
                . '-' . substr(InstalledVersions::getReference('phpstan/phpstan'), 0, 8),
            Package::packageVersion('phpstan/phpstan', true, true)
        );
        $this->assertNull(Package::packageVersion('composer/composer'));
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
