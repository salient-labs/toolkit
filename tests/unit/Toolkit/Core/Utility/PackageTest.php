<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Composer\InstalledVersions;
use Salient\Core\Facade\Event;
use Salient\Core\Utility\Event\PackageDataReceivedEvent;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Package;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Utility\Package
 * @covers \Salient\Core\Utility\Event\PackageDataReceivedEvent
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

    private const DEV_NULL_REF_PACKAGE = [
        'name' => 'acme/sync',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => null,
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => true,
    ];

    private const PROD_NULL_REF_PACKAGE = [
        'name' => 'acme/sync',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => false,
    ];

    /** @var array<string,mixed>|null */
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

    public function testReference(): void
    {
        $this->assertSame('c44d26d4', Package::reference());
        $this->assertSame('c44d26d440cec2096284e20478a5ff984b65fa54', Package::reference(false));
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
            [
                'dev-main',
                self::DEV_NULL_REF_PACKAGE,
            ],
            [
                'dev-main',
                self::DEV_NULL_REF_PACKAGE,
                false,
            ],
            [
                'dev-main',
                self::DEV_NULL_REF_PACKAGE,
                true,
                true,
            ],
            [
                'dev-main',
                self::DEV_NULL_REF_PACKAGE,
                false,
                true,
            ],
            [
                '1.0.0+no-version-set',
                self::PROD_NULL_REF_PACKAGE,
            ],
            [
                '1.0.0.0',
                self::PROD_NULL_REF_PACKAGE,
                false,
            ],
            [
                '1.0.0+no-version-set',
                self::PROD_NULL_REF_PACKAGE,
                true,
                true,
            ],
            [
                '1.0.0.0',
                self::PROD_NULL_REF_PACKAGE,
                false,
                true,
            ],
        ];
    }

    public function testPath(): void
    {
        $this->assertTrue(File::same($this->getPackagePath(), Package::path()));
    }

    public function testPackageReference(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{9,}$/Di',
            $longRef = (string) InstalledVersions::getReference('phpunit/phpunit')
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}$/Di',
            $shortRef = substr($longRef, 0, 8)
        );

        $this->assertSame($longRef, Package::packageReference('phpunit/phpunit', false));
        $this->assertSame($shortRef, Package::packageReference('phpunit/phpunit'));
        $this->assertNull(Package::packageReference('composer/composer'));
    }

    public function testPackageVersion(): void
    {
        // Test against firebase/php-jwt for its vX.Y.Z version numbering
        $this->assertNotNull($version = InstalledVersions::getVersion('firebase/php-jwt'));
        $this->assertNotNull($prettyVersion = InstalledVersions::getPrettyVersion('firebase/php-jwt'));
        $this->assertNotSame($version, $prettyVersion);
        $this->assertMatchesRegularExpression('/^v[0-9]/i', $prettyVersion);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}$/Di',
            $shortRef = substr((string) InstalledVersions::getReference('firebase/php-jwt'), 0, 8)
        );

        $this->assertSame($version, Package::packageVersion('firebase/php-jwt', false));
        $this->assertSame($prettyVersion, Package::packageVersion('firebase/php-jwt'));
        $this->assertSame("$version-$shortRef", Package::packageVersion('firebase/php-jwt', false, true));
        $this->assertSame("$prettyVersion-$shortRef", Package::packageVersion('firebase/php-jwt', true, true));
        $this->assertNull(Package::packageVersion('composer/composer'));
    }

    public function testPackagePath(): void
    {
        // `InstalledVersions::getInstallPath()` may return a non-existent
        // location in phpstan.phar, so don't perform any filesystem tests here
        $this->assertNotNull($dir = InstalledVersions::getInstallPath('phpunit/phpunit'));
        $this->assertSame($dir, Package::packagePath('phpunit/phpunit'));
        $this->assertNull(Package::packagePath('composer/composer'));
    }

    /**
     * @dataProvider classPathProvider
     *
     * @param class-string $class
     */
    public function testClassPath(?string $expected, string $class): void
    {
        if ($expected === null) {
            $this->assertNull(Package::classPath($class));
        } else {
            $this->assertTrue(File::same($expected, (string) Package::classPath($class)));
        }
    }

    /**
     * @return array<string,array{string|null,string}>
     */
    public static function classPathProvider(): array
    {
        return [
            __CLASS__ => [__FILE__, __CLASS__],
            'DoesNotExist' => [null, 'DoesNotExist'],
        ];
    }

    /**
     * @dataProvider namespacePathProvider
     */
    public function testNamespacePath(?string $expected, string $namespace): void
    {
        if ($expected === null) {
            $this->assertNull(Package::namespacePath($namespace));
        } else {
            $this->assertSame(
                $this->directorySeparatorToNative($expected),
                $this->directorySeparatorToNative((string) Package::namespacePath($namespace))
            );
        }
    }

    /**
     * @return array<string,array{string|null,string}>
     */
    public static function namespacePathProvider(): array
    {
        return [
            __NAMESPACE__ =>
                [__DIR__, __NAMESPACE__],
            __NAMESPACE__ . '\Package\Child' =>
                [__DIR__ . '/Package/Child', __NAMESPACE__ . '\Package\Child'],
            __NAMESPACE__ . '\Package' =>
                [self::getFixturesPath(__CLASS__), __NAMESPACE__ . '\Package'],
            'MyNamespace\DoesNotExist' =>
                [null, 'MyNamespace\DoesNotExist'],
        ];
    }

    public static function setUpBeforeClass(): void
    {
        self::$ListenerId = Event::getInstance()->listen(
            static function (PackageDataReceivedEvent $event): void {
                if (
                    self::$RootPackage !== null
                    && $event->isMethod(InstalledVersions::class, 'getRootPackage')
                    // For code coverage only
                    && $event->getArguments() === []
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

    protected function setUp(): void
    {
        self::$RootPackage = self::DEV_PACKAGE;
    }
}
