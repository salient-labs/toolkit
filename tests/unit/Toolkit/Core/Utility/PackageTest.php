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
        'name' => 'salient/toolkit',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => 'c44d26d440cec2096284e20478a5ff984b65fa54',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../../../../',
        'aliases' => [],
        'dev' => true,
    ];

    private const PROD_PACKAGE = [
        'name' => 'salient/toolkit',
        'pretty_version' => 'v0.99.12',
        'version' => '0.99.12.0',
        'reference' => '5eb65f653bcbfbfa0485d2c4a56ae37636a6629e',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../../../../',
        'aliases' => [],
        'dev' => false,
    ];

    /**
     * @var array<string,mixed>|null
     */
    private static ?array $RootPackage = null;

    private static int $ListenerId;

    public function testHasDevPackages(): void
    {
        $this->assertTrue(Package::hasDevPackages());
    }

    public function testName(): void
    {
        $this->assertSame('salient/toolkit', Package::name());
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
        self::$RootPackage = $package;
        $this->assertSame($expected, Package::version($pretty, $withReference));
    }

    /**
     * @return array<array{string,array<string,mixed>,2?:bool,3?:bool}>
     */
    public static function versionProvider(): array
    {
        return [
            [
                'dev-main@c44d26d4',
                self::DEV_PACKAGE,
            ],
            [
                'dev-main@c44d26d4',
                self::DEV_PACKAGE,
                false,
            ],
            [
                'dev-main@c44d26d4',
                self::DEV_PACKAGE,
                true,
                true,
            ],
            [
                'dev-main@c44d26d4',
                self::DEV_PACKAGE,
                false,
                true,
            ],
            [
                'v0.99.12',
                self::PROD_PACKAGE,
            ],
            [
                '0.99.12.0',
                self::PROD_PACKAGE,
                false,
            ],
            [
                'v0.99.12-5eb65f65',
                self::PROD_PACKAGE,
                true,
                true,
            ],
            [
                '0.99.12.0-5eb65f65',
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
            InstalledVersions::getPrettyVersion('phpunit/phpunit'),
            Package::packageVersion('phpunit/phpunit')
        );
        $this->assertSame(
            InstalledVersions::getPrettyVersion('phpunit/phpunit')
                . '-' . substr((string) InstalledVersions::getReference('phpunit/phpunit'), 0, 8),
            Package::packageVersion('phpunit/phpunit', true, true)
        );
        $this->assertNull(Package::packageVersion('composer/composer'));
    }

    protected function setUp(): void
    {
        self::$RootPackage = self::DEV_PACKAGE;
    }

    public static function setUpBeforeClass(): void
    {
        self::$ListenerId = Event::getInstance()->listen(
            static function (PackageDataReceivedEvent $event): void {
                if (
                    self::$RootPackage !== null &&
                    $event->isMethod(InstalledVersions::class, 'getRootPackage')
                ) {
                    $event->setData(self::$RootPackage);
                }
            }
        );
    }

    public static function tearDownAfterClass(): void
    {
        Event::removeListener(self::$ListenerId);
    }
}
