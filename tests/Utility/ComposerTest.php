<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Composer\InstalledVersions;
use Lkrms\Facade\Composer;

final class ComposerTest extends \Lkrms\Tests\TestCase
{
    public function testHasDevDependencies()
    {
        $this->assertTrue(Composer::hasDevDependencies());
    }

    public function testGetRootPackageName()
    {
        $this->assertSame('lkrms/util', Composer::getRootPackageName());
    }

    public function testGetRootPackageReference()
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetRootPackageVersion()
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetRootPackagePath()
    {
        $this->assertSame(realpath(dirname(__DIR__, 2)), Composer::getRootPackagePath());
    }

    public function testGetPackageReference()
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetPackageVersion()
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

    public function testGetPackagePath()
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetClassPath()
    {
        $this->expectNotToPerformAssertions();
    }

    public function testGetNamespacePath()
    {
        $this->expectNotToPerformAssertions();
    }
}
