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
        ['branch' => $branch, 'commit' => $commit] = $this->getRepoState();
        $this->assertSame("dev-$branch@$commit", Composer::getRootPackageVersion());
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
        ['branch' => $branch, 'commit' => $commit] = $this->getRepoState();
        $this->assertSame("dev-$branch@$commit", Composer::getPackageVersion());
        $this->assertSame(InstalledVersions::getPrettyVersion('phpstan/phpstan'),
                          Composer::getPackageVersion('phpstan/phpstan', true));
        $this->assertSame(InstalledVersions::getPrettyVersion('phpstan/phpstan')
                              . '-' . substr(InstalledVersions::getReference('phpstan/phpstan'), 0, 8),
                          Composer::getPackageVersion('phpstan/phpstan', true, true));
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

    private function getRepoState()
    {
        $top = dirname(__DIR__, 2);
        if (!is_dir($top . '/.git')) {
            $this->fail("Not a git repository: $top");
        }
        $cwd = getcwd();
        chdir($top);
        try {
            $branch = $this->shellExec('git rev-parse --verify --abbrev-ref HEAD');
            if (!$branch || $branch === 'HEAD') {
                $this->fail('Error getting current branch');
            }
            $commit = $this->shellExec('git rev-parse --verify --short=8 HEAD');
            if (!$commit) {
                $this->fail('Error getting current commit');
            }
        } finally {
            chdir($cwd);
        }

        return [
            'branch' => $branch,
            'commit' => $commit,
        ];
    }
}
