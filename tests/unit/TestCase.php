<?php declare(strict_types=1);

namespace Lkrms\Tests;

use Lkrms\Utility\Pcre;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Expect an exception if a given value is a string
     *
     * If `$expected` is a string with no commas, it is passed to
     * {@see expectException()}. If it is a string with at least one comma, text
     * before the first comma is passed to {@see expectException()}, and text
     * after the comma is passed to {@see expectExceptionMessage()}.
     *
     * If `$expected` is not a string, no action is taken.
     *
     * @param class-string<\Throwable>|string|mixed $expected
     */
    public function maybeExpectException($expected): void
    {
        if (!is_string($expected)) {
            return;
        }
        $split = explode(',', $expected, 2);
        if (count($split) === 2) {
            $expected = $split[0];
            $this->expectExceptionMessage($split[1]);
        }
        $this->expectException($expected);
    }

    /**
     * Get the path to the fixtures directory for a class
     */
    public static function getFixturesPath(string $class): string
    {
        return dirname(__DIR__)
            . '/fixtures/'
            . Pcre::replace(
                ['/^Lkrms\\\\(?|Tests\\\\(.+)Test$|(.+))/', '/\\\\/'],
                ['$1', '/'],
                $class
            );
    }

    /**
     * Replace newlines in a string with PHP_EOL
     */
    public static function eolToNative(?string $string): ?string
    {
        return $string === null
            ? null
            : str_replace("\n", \PHP_EOL, $string);
    }

    /**
     * Replace directory separators in a string with DIRECTORY_SEPARATOR
     */
    public static function directorySeparatorToNative(?string $string): ?string
    {
        return $string === null
            ? null
            : str_replace('/', \DIRECTORY_SEPARATOR, $string);
    }
}
