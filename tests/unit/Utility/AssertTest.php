<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Exception\AssertionFailedException;
use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Utility\Assert;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;

final class AssertTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider fileExistsProvider
     */
    public function testFileExists(bool $exists, string $filename): void
    {
        if ($exists) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(FilesystemErrorException::class);
        }
        Assert::fileExists($filename);
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function fileExistsProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        return [
            [true, "$dir/"],
            [true, "$dir/dir"],
            [true, "$dir/dir/file"],
            [false, "$dir/does_not_exist"],
        ];
    }

    /**
     * @dataProvider isFileProvider
     */
    public function testIsFile(bool $isFile, string $filename): void
    {
        if ($isFile) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(FilesystemErrorException::class);
        }
        Assert::isFile($filename);
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isFileProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        return [
            [false, "$dir/"],
            [false, "$dir/dir"],
            [true, "$dir/dir/file"],
            [false, "$dir/does_not_exist"],
        ];
    }

    /**
     * @dataProvider isDirProvider
     */
    public function testIsDir(bool $isDir, string $filename): void
    {
        if ($isDir) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(FilesystemErrorException::class);
        }
        Assert::isDir($filename);
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isDirProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        return [
            [true, "$dir/"],
            [true, "$dir/dir"],
            [false, "$dir/dir/file"],
            [false, "$dir/does_not_exist"],
        ];
    }

    /**
     * @dataProvider notEmptyProvider
     *
     * @param mixed $value
     */
    public function testNotEmpty(bool $notEmpty, $value, ?string $name = null, ?string $message = null): void
    {
        if ($notEmpty) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
        }
        Assert::notEmpty($value, $name);
    }

    /**
     * @return array<array{bool,mixed,2?:string|null,3?:string|null}>
     */
    public static function notEmptyProvider(): array
    {
        return [
            [false, null, 'argument', 'argument cannot be empty'],
            [false, [], 'array', 'array cannot be empty'],
            [true, [null]],
            [false, '', null, 'value cannot be empty'],
            [false, '0', '$string', '$string cannot be empty'],
            [true, '1'],
            [false, 0, 'integer', 'integer cannot be empty'],
            [true, 1],
            [false, false, 'boolean', 'boolean cannot be empty'],
            [true, true],
        ];
    }

    /**
     * @dataProvider instanceOfProvider
     *
     * @param mixed $value
     */
    public function testInstanceOf(bool $instanceOf, $value, string $class, ?string $name = null, ?string $message = null): void
    {
        if ($instanceOf) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
        }
        Assert::instanceOf($value, $class, $name);
    }

    /**
     * @return array<array{bool,mixed,string,3?:string|null,4?:string|null}>
     */
    public static function instanceOfProvider(): array
    {
        return [
            [false, null, stdClass::class, '$instance', '$instance must be an instance of ' . stdClass::class],
            [true, new stdClass(), stdClass::class],
            [true, new class extends stdClass {}, stdClass::class],
            [false, new DateTime(), DateTimeImmutable::class, null, 'value must be an instance of ' . DateTimeImmutable::class],
            [true, new DateTime(), DateTimeInterface::class],
        ];
    }

    /**
     * @dataProvider isArrayProvider
     *
     * @param mixed $value
     */
    public function testIsArray(bool $isArray, $value, ?string $name = null, ?string $message = null): void
    {
        if ($isArray) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
        }
        Assert::isArray($value, $name);
    }

    /**
     * @return array<array{bool,mixed,2?:string|null,3?:string|null}>
     */
    public static function isArrayProvider(): array
    {
        return [
            [true, []],
            [true, [0]],
            [true, [null]],
            [true, ['foo' => 'bar']],
            [false, null, '$arg', '$arg must be an array'],
            [false, '', null, 'value must be an array'],
            [false, '0'],
            [false, '1'],
            [false, 0],
            [false, 1],
            [false, 3.14],
            [false, false],
            [false, true],
            [false, new stdClass()],
        ];
    }

    /**
     * @dataProvider isIntProvider
     *
     * @param mixed $value
     */
    public function testIsInt(bool $isInt, $value, ?string $name = null, ?string $message = null): void
    {
        if ($isInt) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
        }
        Assert::isInt($value, $name);
    }

    /**
     * @return array<array{bool,mixed,2?:string|null,3?:string|null}>
     */
    public static function isIntProvider(): array
    {
        return [
            [true, -1],
            [true, 0],
            [true, 1],
            [false, null, '$arg', '$arg must be an integer'],
            [false, '', null, 'value must be an integer'],
            [false, '0'],
            [false, '1'],
            [false, 3.14],
            [false, false],
            [false, true],
        ];
    }

    /**
     * @dataProvider isStringProvider
     *
     * @param mixed $value
     */
    public function testIsString(bool $isString, $value, ?string $name = null, ?string $message = null): void
    {
        if ($isString) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
        }
        Assert::isString($value, $name);
    }

    /**
     * @return array<array{bool,mixed,2?:string|null,3?:string|null}>
     */
    public static function isStringProvider(): array
    {
        return [
            [true, ''],
            [true, '0'],
            [true, '1'],
            [true, 'null'],
            [false, null, '$arg', '$arg must be a string'],
            [false, -1, null, 'value must be a string'],
            [false, 0],
            [false, 1],
            [false, 3.14],
            [false, false],
            [false, true],
        ];
    }

    /**
     * @dataProvider isMatchProvider
     *
     * @param mixed $value
     */
    public function testIsMatch(bool $isMatch, $value, string $pattern, ?string $name = null, ?string $message = null): void
    {
        if ($isMatch) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
        }
        Assert::isMatch($value, $pattern, $name);
    }

    /**
     * @return array<array{bool,mixed,string,3?:string|null,4?:string|null}>
     */
    public static function isMatchProvider(): array
    {
        return [
            [true, '', '/.*/'],
            [true, 'Text', '/^t.+t$/i'],
            [false, '', '/.+/'],
            [false, null, '/.+/', '$arg', '$arg must match regular expression: /.+/'],
            [false, 0, '/.*/', null, 'value must match regular expression: /.*/'],
            [false, 1, '/.*/'],
            [false, false, '/.*/'],
            [false, true, '/.*/'],
        ];
    }

    public function testRunningOnCli(): void
    {
        if (PHP_SAPI === 'cli') {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            $this->expectExceptionMessage('CLI required');
        }
        Assert::runningOnCli();
    }

    public function testArgvIsDeclared(): void
    {
        if (ini_get('register_argc_argv')) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(AssertionFailedException::class);
            $this->expectExceptionMessage('register_argc_argv must be enabled');
        }
        Assert::argvIsDeclared();
    }
}
