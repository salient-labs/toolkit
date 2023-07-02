<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Facade\Env;
use RuntimeException;

final class EnvironmentTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider getProvider
     *
     * @param string|int|bool|null $expected
     * @param array{default?:string|int|bool|null,value?:string|int|bool|null} $values
     */
    public function testGet($expected, string $method, array $values = [], ?string $ex = null)
    {
        $this->_testGet($expected, $method, $values, $ex);
    }

    /**
     * @dataProvider getNullableProvider
     *
     * @param string|int|bool|null $expected
     * @param array{default?:string|int|bool|null,value?:string|int|bool|null} $values
     */
    public function testGetNullable($expected, string $method, array $values = [], ?string $ex = null)
    {
        $this->_testGet($expected, $method, $values, $ex);
    }

    /**
     * @param string|int|bool|null $expected
     * @param array{default?:string|int|bool|null,value?:string|int|bool|null} $values
     */
    private function _testGet($expected, string $method, array $values = [], ?string $ex = null)
    {
        $env = $_ENV;

        if (array_key_exists('value', $values)) {
            $_ENV[__METHOD__] = $values['value'];
        } else {
            unset($_ENV[__METHOD__]);
        }

        $args = [];
        if (array_key_exists('default', $values)) {
            $args[] = $values['default'];
        }

        if ($ex) {
            $this->expectException($ex);
        }
        $this->assertSame($expected, Env::$method(__METHOD__, ...$args));

        $_ENV = $env;
    }

    public static function getProvider()
    {
        return [
            // get
            'empty string -> string' => ['', 'get', ['value' => '']],
            'whitespace -> string' => [' ', 'get', ['value' => ' ']],
            'text -> string' => ['a', 'get', ['value' => 'a']],
            'zero -> string' => ['0', 'get', ['value' => '0']],
            'empty string -> string, with default' => ['', 'get', ['value' => '', 'default' => '1']],
            'whitespace -> string, with default' => [' ', 'get', ['value' => ' ', 'default' => '1']],
            'text -> string, with default' => ['a', 'get', ['value' => 'a', 'default' => '1']],
            'zero -> string, with default' => ['0', 'get', ['value' => '0', 'default' => '1']],
            'unset -> string, no default' => [null, 'get', [], RuntimeException::class],
            'unset -> string, default null' => [null, 'get', ['default' => null]],
            'unset -> string, default empty' => ['', 'get', ['default' => '']],
            'unset -> string, default text' => ['a', 'get', ['default' => 'a']],
            'unset -> string, default zero' => ['0', 'get', ['default' => '0']],

            // getInt
            'empty string -> int' => [null, 'getInt', ['value' => '']],
            'whitespace -> int' => [null, 'getInt', ['value' => ' ']],
            'text -> int' => [0, 'getInt', ['value' => 'a']],
            'number -> int' => [42, 'getInt', ['value' => '42']],
            'zero -> int' => [0, 'getInt', ['value' => '0']],
            'empty string -> int, with default' => [1, 'getInt', ['value' => '', 'default' => 1]],
            'whitespace -> int, with default' => [1, 'getInt', ['value' => ' ', 'default' => 1]],
            'text -> int, with default' => [0, 'getInt', ['value' => 'a', 'default' => 1]],
            'number -> int, with default' => [42, 'getInt', ['value' => '42', 'default' => 1]],
            'zero -> int, with default' => [0, 'getInt', ['value' => '0', 'default' => 1]],
            'unset -> int, no default' => [null, 'getInt', [], RuntimeException::class],
            'unset -> int, default null' => [null, 'getInt', ['default' => null]],
            'unset -> int, default number' => [42, 'getInt', ['default' => 42]],
            'unset -> int, default zero' => [0, 'getInt', ['default' => 0]],

            // getBool
            'empty string -> bool' => [null, 'getBool', ['value' => '']],
            'whitespace -> bool' => [null, 'getBool', ['value' => ' ']],
            'text -> bool' => [true, 'getBool', ['value' => 'a']],
            'number -> bool' => [true, 'getBool', ['value' => '42']],
            "'0' -> bool" => [false, 'getBool', ['value' => '0']],
            "'1' -> bool" => [true, 'getBool', ['value' => '1']],
            "'f' -> bool" => [false, 'getBool', ['value' => 'f']],
            "'false' -> bool" => [false, 'getBool', ['value' => 'false']],
            "'t' -> bool" => [true, 'getBool', ['value' => 't']],
            "'true' -> bool" => [true, 'getBool', ['value' => 'true']],
            "'n' -> bool" => [false, 'getBool', ['value' => 'n']],
            "'no' -> bool" => [false, 'getBool', ['value' => 'no']],
            "'y' -> bool" => [true, 'getBool', ['value' => 'y']],
            "'yes' -> bool" => [true, 'getBool', ['value' => 'yes']],
            "'off' -> bool" => [false, 'getBool', ['value' => 'off']],
            "'on' -> bool" => [true, 'getBool', ['value' => 'on']],
            'empty string -> bool, with default' => [true, 'getBool', ['value' => '', 'default' => true]],
            'whitespace -> bool, with default' => [true, 'getBool', ['value' => ' ', 'default' => true]],
            'text -> bool, with default' => [true, 'getBool', ['value' => 'a', 'default' => false]],
            'number -> bool, with default' => [true, 'getBool', ['value' => '42', 'default' => false]],
            'zero -> bool, with default' => [false, 'getBool', ['value' => '0', 'default' => true]],
            'unset -> bool, no default' => [null, 'getBool', [], RuntimeException::class],
            'unset -> bool, default null' => [null, 'getBool', ['default' => null]],
            'unset -> bool, default false' => [false, 'getBool', ['default' => false]],
            'unset -> bool, default true' => [true, 'getBool', ['default' => true]],
        ];
    }

    public static function getNullableProvider()
    {
        return [
            // getNullable
            'empty string -> string' => [null, 'getNullable', ['value' => '']],
            'whitespace -> string' => [null, 'getNullable', ['value' => ' ']],
            'text -> string' => ['a', 'getNullable', ['value' => 'a']],
            'zero -> string' => ['0', 'getNullable', ['value' => '0']],
            'empty string -> string, with default' => [null, 'getNullable', ['value' => '', 'default' => '1']],
            'whitespace -> string, with default' => [null, 'getNullable', ['value' => ' ', 'default' => '1']],
            'text -> string, with default' => ['a', 'getNullable', ['value' => 'a', 'default' => '1']],
            'zero -> string, with default' => ['0', 'getNullable', ['value' => '0', 'default' => '1']],
            'unset -> string, no default' => [null, 'getNullable', [], RuntimeException::class],
            'unset -> string, default null' => [null, 'getNullable', ['default' => null]],
            'unset -> string, default empty' => ['', 'getNullable', ['default' => '']],
            'unset -> string, default text' => ['a', 'getNullable', ['default' => 'a']],
            'unset -> string, default zero' => ['0', 'getNullable', ['default' => '0']],

            // getNullableInt
            'empty string -> int' => [null, 'getNullableInt', ['value' => '']],
            'whitespace -> int' => [null, 'getNullableInt', ['value' => ' ']],
            'text -> int' => [0, 'getNullableInt', ['value' => 'a']],
            'number -> int' => [42, 'getNullableInt', ['value' => '42']],
            'zero -> int' => [0, 'getNullableInt', ['value' => '0']],
            'empty string -> int, with default' => [null, 'getNullableInt', ['value' => '', 'default' => 1]],
            'whitespace -> int, with default' => [null, 'getNullableInt', ['value' => ' ', 'default' => 1]],
            'text -> int, with default' => [0, 'getNullableInt', ['value' => 'a', 'default' => 1]],
            'number -> int, with default' => [42, 'getNullableInt', ['value' => '42', 'default' => 1]],
            'zero -> int, with default' => [0, 'getNullableInt', ['value' => '0', 'default' => 1]],
            'unset -> int, no default' => [null, 'getNullableInt', [], RuntimeException::class],
            'unset -> int, default null' => [null, 'getNullableInt', ['default' => null]],
            'unset -> int, default number' => [42, 'getNullableInt', ['default' => 42]],
            'unset -> int, default zero' => [0, 'getNullableInt', ['default' => 0]],

            // getNullableBool
            'empty string -> bool' => [null, 'getNullableBool', ['value' => '']],
            'whitespace -> bool' => [null, 'getNullableBool', ['value' => ' ']],
            'text -> bool' => [true, 'getNullableBool', ['value' => 'a']],
            'number -> bool' => [true, 'getNullableBool', ['value' => '42']],
            "'0' -> bool" => [false, 'getNullableBool', ['value' => '0']],
            "'1' -> bool" => [true, 'getNullableBool', ['value' => '1']],
            "'f' -> bool" => [false, 'getNullableBool', ['value' => 'f']],
            "'false' -> bool" => [false, 'getNullableBool', ['value' => 'false']],
            "'t' -> bool" => [true, 'getNullableBool', ['value' => 't']],
            "'true' -> bool" => [true, 'getNullableBool', ['value' => 'true']],
            "'n' -> bool" => [false, 'getNullableBool', ['value' => 'n']],
            "'no' -> bool" => [false, 'getNullableBool', ['value' => 'no']],
            "'y' -> bool" => [true, 'getNullableBool', ['value' => 'y']],
            "'yes' -> bool" => [true, 'getNullableBool', ['value' => 'yes']],
            "'off' -> bool" => [false, 'getNullableBool', ['value' => 'off']],
            "'on' -> bool" => [true, 'getNullableBool', ['value' => 'on']],
            'empty string -> bool, with default' => [null, 'getNullableBool', ['value' => '', 'default' => true]],
            'whitespace -> bool, with default' => [null, 'getNullableBool', ['value' => ' ', 'default' => true]],
            'text -> bool, with default' => [true, 'getNullableBool', ['value' => 'a', 'default' => false]],
            'number -> bool, with default' => [true, 'getNullableBool', ['value' => '42', 'default' => false]],
            'zero -> bool, with default' => [false, 'getNullableBool', ['value' => '0', 'default' => true]],
            'unset -> bool, no default' => [null, 'getNullableBool', [], RuntimeException::class],
            'unset -> bool, default null' => [null, 'getNullableBool', ['default' => null]],
            'unset -> bool, default false' => [false, 'getNullableBool', ['default' => false]],
            'unset -> bool, default true' => [true, 'getNullableBool', ['default' => true]],
        ];
    }
}
