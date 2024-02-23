<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Composer\InstalledVersions;
use Salient\Core\Event\PackageDataReceivedEvent;
use Salient\Core\Facade\Event;
use Salient\Core\Utility\Package;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Utility\Package
 */
final class PackageTest extends TestCase
{
    private const DEV_PACKAGE = [
        'name' => 'lkrms/util',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'aee6e2bfc8c3c8daa5b04bc26e5b2ae9f51b036f',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../../../',
        'aliases' => [],
        'dev' => true,
    ];

    private const PROD_PACKAGE = [
        'name' => 'lkrms/util',
        'pretty_version' => 'v0.21.34',
        'version' => '0.21.34.0',
        'reference' => 'b0a4391f5feb5cf10a3ba2b018f3110da6d915d9',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../../../',
        'aliases' => [],
        'dev' => false,
    ];

    public function testHasDevPackages(): void
    {
        $this->assertTrue(Package::hasDevPackages());
    }

    public function testName(): void
    {
        $this->assertSame('lkrms/util', Package::name());
    }

    /**
     * @dataProvider versionProvider
     *
     * @param array<string,mixed> $package
     */
    public function testVersion(
        string $expected,
        array $package,
        bool $pretty = true,
        bool $withReference = false
    ): void {
        $listenerId = Event::listen(
            function (PackageDataReceivedEvent $event) use ($package): void {
                if ($event->isMethod('getRootPackage')) {
                    $event->setData($package);
                }
            }
        );

        try {
            $this->assertSame($expected, Package::version($pretty, $withReference));
        } finally {
            Event::removeListener($listenerId);
        }
    }

    /**
     * @return array<array{string,array<string,mixed>,2?:bool,3?:bool}>
     */
    public static function versionProvider(): array
    {
        return [
            [
                'dev-main@aee6e2bf',
                self::DEV_PACKAGE,
            ],
            [
                'dev-main@aee6e2bf',
                self::DEV_PACKAGE,
                false,
            ],
            [
                'dev-main@aee6e2bf',
                self::DEV_PACKAGE,
                true,
                true,
            ],
            [
                'dev-main@aee6e2bf',
                self::DEV_PACKAGE,
                false,
                true,
            ],
            [
                'v0.21.34',
                self::PROD_PACKAGE,
            ],
            [
                '0.21.34.0',
                self::PROD_PACKAGE,
                false,
            ],
            [
                'v0.21.34-b0a4391f',
                self::PROD_PACKAGE,
                true,
                true,
            ],
            [
                '0.21.34.0-b0a4391f',
                self::PROD_PACKAGE,
                false,
                true,
            ],
        ];
    }

    public function testGetRootPackagePath(): void
    {
        $this->assertSame(realpath(dirname(__DIR__, 5)), Package::path());
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
}
