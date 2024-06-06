<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Core\Utility\Exception\InvalidEnvFileSyntaxException;
use Salient\Core\Utility\Exception\InvalidEnvironmentException;
use Salient\Core\Utility\Env;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Utility\Env
 */
final class EnvTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testLoad(): void
    {
        $dir = self::getFixturesPath(__CLASS__);
        $expected = [
            'LANG' => 'en_US.UTF-8',
            'TZ' => 'Australia/Sydney',
            'app_client_id' => 'd8f024b9-1dfb-4dde-8f29-db98eefa317c',
            'app_secret' => "'^K&4nnE",
        ];
        $this->unsetKeys($expected);
        Env::load($dir . '/example.env');
        $this->assertSame($expected, $this->getKeys($expected));

        $expected = [
            'quoted_whitespace' => 'Foo B. Baz',
            'escaped_dollar1' => '$SECRET',
            'escaped_dollar2' => '$SECRET',
            'escaped_double_quote' => 'Foo "Bar"',
            'escaped_single_quote' => "Foo's Bar",
            'good_mixed_quotes' => '\'$this\' is not an "app"',
            'good_backtick' => '`echo foo`',
            'glob4' => '[0-9][0-9]-20??????-*.ext',
        ];
        $this->unsetKeys($expected);
        Env::load($dir . '/valid.env');
        $this->assertSame($expected, $this->getKeys($expected));

        $file = $dir . '/invalid.env';
        $lines = ['Unable to load .env file:'];
        foreach ([1, 3, 5, 6, 9, 11, 13, 14, 15, 17, 19, 20, 21, 23] as $line) {
            $lines[] = "- invalid syntax at $file:$line";
        }
        $this->expectException(InvalidEnvFileSyntaxException::class);
        $this->expectExceptionMessage(implode("\n", $lines));
        Env::load($dir . '/invalid.env');
    }

    /**
     * @param array<string,mixed> $expected
     */
    private function unsetKeys(array $expected): void
    {
        foreach (array_keys($expected) as $key) {
            Env::unset($key);
        }
    }

    /**
     * @param array<string,mixed> $expected
     * @return array<string,string|null>
     */
    private function getKeys(array $expected): array
    {
        foreach (array_keys($expected) as $key) {
            $actual[$key] = Env::get($key, null);
        }
        return $actual ?? [];
    }

    /**
     * @dataProvider getProvider
     * @dataProvider getNullableProvider
     * @backupGlobals enabled
     *
     * @param string[]|int[]|string|int|bool|null $expected
     * @param array{default?:string[]|int[]|string|int|bool|null,value?:string} $values
     */
    public function testGet($expected, string $method, array $values = [], ?string $ex = null): void
    {
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
    }

    /**
     * @return array<string,array{0:string[]|int[]|string|int|bool|null,1:string,2?:array{default?:string[]|int[]|string|int|bool|null,value?:string},3?:string}>
     */
    public static function getProvider(): array
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
            'unset -> string, no default' => [null, 'get', [], InvalidEnvironmentException::class],
            'unset -> string, default null' => [null, 'get', ['default' => null]],
            'unset -> string, default empty' => ['', 'get', ['default' => '']],
            'unset -> string, default text' => ['a', 'get', ['default' => 'a']],
            'unset -> string, default zero' => ['0', 'get', ['default' => '0']],
            // getInt
            'empty string -> int' => [null, 'getInt', ['value' => ''], InvalidEnvironmentException::class],
            'whitespace -> int' => [null, 'getInt', ['value' => ' '], InvalidEnvironmentException::class],
            'text -> int' => [null, 'getInt', ['value' => 'a'], InvalidEnvironmentException::class],
            'number -> int' => [42, 'getInt', ['value' => '42']],
            'zero -> int' => [0, 'getInt', ['value' => '0']],
            'empty string -> int, with default' => [null, 'getInt', ['value' => '', 'default' => 1], InvalidEnvironmentException::class],
            'whitespace -> int, with default' => [null, 'getInt', ['value' => ' ', 'default' => 1], InvalidEnvironmentException::class],
            'text -> int, with default' => [null, 'getInt', ['value' => 'a', 'default' => 1], InvalidEnvironmentException::class],
            'number -> int, with default' => [42, 'getInt', ['value' => '42', 'default' => 1]],
            'zero -> int, with default' => [0, 'getInt', ['value' => '0', 'default' => 1]],
            'unset -> int, no default' => [null, 'getInt', [], InvalidEnvironmentException::class],
            'unset -> int, default null' => [null, 'getInt', ['default' => null]],
            'unset -> int, default number' => [42, 'getInt', ['default' => 42]],
            'unset -> int, default zero' => [0, 'getInt', ['default' => 0]],
            // getBool
            'empty string -> bool' => [false, 'getBool', ['value' => '']],
            'whitespace -> bool' => [false, 'getBool', ['value' => ' ']],
            'text -> bool' => [null, 'getBool', ['value' => 'a'], InvalidEnvironmentException::class],
            'number -> bool' => [null, 'getBool', ['value' => '42'], InvalidEnvironmentException::class],
            "'0' -> bool" => [false, 'getBool', ['value' => '0']],
            "'1' -> bool" => [true, 'getBool', ['value' => '1']],
            "'f' -> bool" => [null, 'getBool', ['value' => 'f'], InvalidEnvironmentException::class],
            "'false' -> bool" => [false, 'getBool', ['value' => 'false']],
            "'t' -> bool" => [null, 'getBool', ['value' => 't'], InvalidEnvironmentException::class],
            "'true' -> bool" => [true, 'getBool', ['value' => 'true']],
            "'n' -> bool" => [false, 'getBool', ['value' => 'n']],
            "'no' -> bool" => [false, 'getBool', ['value' => 'no']],
            "'y' -> bool" => [true, 'getBool', ['value' => 'y']],
            "'yes' -> bool" => [true, 'getBool', ['value' => 'yes']],
            "'off' -> bool" => [false, 'getBool', ['value' => 'off']],
            "'on' -> bool" => [true, 'getBool', ['value' => 'on']],
            'empty string -> bool, with default' => [false, 'getBool', ['value' => '', 'default' => true]],
            'whitespace -> bool, with default' => [false, 'getBool', ['value' => ' ', 'default' => true]],
            'text -> bool, with default' => [null, 'getBool', ['value' => 'a', 'default' => false], InvalidEnvironmentException::class],
            'number -> bool, with default' => [null, 'getBool', ['value' => '42', 'default' => false], InvalidEnvironmentException::class],
            'zero -> bool, with default' => [false, 'getBool', ['value' => '0', 'default' => true]],
            'unset -> bool, no default' => [null, 'getBool', [], InvalidEnvironmentException::class],
            'unset -> bool, default null' => [null, 'getBool', ['default' => null]],
            'unset -> bool, default false' => [false, 'getBool', ['default' => false]],
            'unset -> bool, default true' => [true, 'getBool', ['default' => true]],
            // getList
            'empty string -> list' => [[], 'getList', ['value' => '']],
            'whitespace -> list' => [[' '], 'getList', ['value' => ' ']],
            'text -> list' => [['a'], 'getList', ['value' => 'a']],
            'zero -> list' => [['0'], 'getList', ['value' => '0']],
            'empty string -> list, with default' => [[], 'getList', ['value' => '', 'default' => ['b']]],
            'whitespace -> list, with default' => [[' '], 'getList', ['value' => ' ', 'default' => ['b']]],
            'text -> list, with default' => [['a'], 'getList', ['value' => 'a', 'default' => ['b']]],
            'zero -> list, with default' => [['0'], 'getList', ['value' => '0', 'default' => ['b']]],
            'unset -> list, no default' => [null, 'getList', [], InvalidEnvironmentException::class],
            'unset -> list, default null' => [null, 'getList', ['default' => null]],
            'unset -> list, default empty' => [[], 'getList', ['default' => []]],
            'unset -> list, default text' => [['a'], 'getList', ['default' => ['a']]],
            'unset -> list, default zero' => [['0'], 'getList', ['default' => ['0']]],
            'list + empty string #1 -> list' => [['a', '42', ''], 'getList', ['value' => 'a,42,']],
            'list + empty string #2 -> list' => [['', 'a', '42'], 'getList', ['value' => ',a,42']],
            'list + empty string #3 -> list' => [['a', '', '42'], 'getList', ['value' => 'a,,42']],
            'list + whitespace #1 -> list' => [[' a', '42', 'b'], 'getList', ['value' => ' a,42,b']],
            'list + whitespace #2 -> list' => [['a', '42 ', 'b'], 'getList', ['value' => 'a,42 ,b']],
            'list + whitespace #3 -> list' => [['a', '42', 'b '], 'getList', ['value' => 'a,42,b ']],
            'list + text -> list' => [['a', '42', 'b'], 'getList', ['value' => 'a,42,b']],
            'list + zero -> list' => [['0', '0', '0'], 'getList', ['value' => '0,0,0']],
            // getIntList
            'empty string -> intList' => [[], 'getIntList', ['value' => '']],
            'whitespace -> intList' => [[], 'getIntList', ['value' => ' ']],
            'text -> intList' => [null, 'getIntList', ['value' => 'a'], InvalidEnvironmentException::class],
            'number -> intList' => [[42], 'getIntList', ['value' => '42']],
            'zero -> intList' => [[0], 'getIntList', ['value' => '0']],
            'empty string -> intList, with default' => [[], 'getIntList', ['value' => '', 'default' => [1]]],
            'whitespace -> intList, with default' => [[], 'getIntList', ['value' => ' ', 'default' => [1]]],
            'text -> intList, with default' => [null, 'getIntList', ['value' => 'a', 'default' => [1]], InvalidEnvironmentException::class],
            'number -> intList, with default' => [[42], 'getIntList', ['value' => '42', 'default' => [1]]],
            'zero -> intList, with default' => [[0], 'getIntList', ['value' => '0', 'default' => [1]]],
            'unset -> intList, no default' => [null, 'getIntList', [], InvalidEnvironmentException::class],
            'unset -> intList, default null' => [null, 'getIntList', ['default' => null]],
            'unset -> intList, default empty' => [[], 'getIntList', ['default' => []]],
            'unset -> intList, default number' => [[42], 'getIntList', ['default' => [42]]],
            'unset -> intList, default zero' => [[0], 'getIntList', ['default' => [0]]],
            'list + empty string #1 -> intList' => [null, 'getIntList', ['value' => '28,42,'], InvalidEnvironmentException::class],
            'list + empty string #2 -> intList' => [null, 'getIntList', ['value' => ',28,42'], InvalidEnvironmentException::class],
            'list + empty string #3 -> intList' => [null, 'getIntList', ['value' => '28,,42'], InvalidEnvironmentException::class],
            'list + whitespace #1 -> intList' => [[28, 42, 71], 'getIntList', ['value' => ' 28,42,71']],
            'list + whitespace #2 -> intList' => [[28, 42, 71], 'getIntList', ['value' => '28,42 ,71']],
            'list + whitespace #3 -> intList' => [[28, 42, 71], 'getIntList', ['value' => '28,42,71 ']],
            'list + text -> intList' => [null, 'getIntList', ['value' => '28,42,a'], InvalidEnvironmentException::class],
            'list + number -> intList' => [[28, 42, 71], 'getIntList', ['value' => '28,42,71']],
            'list + zero -> intList' => [[0, 0, 0], 'getIntList', ['value' => '0,0,0']],
        ];
    }

    /**
     * @return array<string,array{0:string[]|int[]|string|int|bool|null,1:string,2?:array{default?:string[]|int[]|string|int|bool|null,value?:string},3?:string}>
     */
    public static function getNullableProvider(): array
    {
        return [
            // getNullable
            '[nullable] empty string -> string' => [null, 'getNullable', ['value' => '']],
            '[nullable] whitespace -> string' => [' ', 'getNullable', ['value' => ' ']],
            '[nullable] text -> string' => ['a', 'getNullable', ['value' => 'a']],
            '[nullable] zero -> string' => ['0', 'getNullable', ['value' => '0']],
            '[nullable] empty string -> string, with default' => [null, 'getNullable', ['value' => '', 'default' => '1']],
            '[nullable] whitespace -> string, with default' => [' ', 'getNullable', ['value' => ' ', 'default' => '1']],
            '[nullable] text -> string, with default' => ['a', 'getNullable', ['value' => 'a', 'default' => '1']],
            '[nullable] zero -> string, with default' => ['0', 'getNullable', ['value' => '0', 'default' => '1']],
            '[nullable] unset -> string, no default' => [null, 'getNullable', [], InvalidEnvironmentException::class],
            '[nullable] unset -> string, default null' => [null, 'getNullable', ['default' => null]],
            '[nullable] unset -> string, default empty' => ['', 'getNullable', ['default' => '']],
            '[nullable] unset -> string, default text' => ['a', 'getNullable', ['default' => 'a']],
            '[nullable] unset -> string, default zero' => ['0', 'getNullable', ['default' => '0']],
            // getNullableInt
            '[nullable] empty string -> int' => [null, 'getNullableInt', ['value' => '']],
            '[nullable] whitespace -> int' => [null, 'getNullableInt', ['value' => ' ']],
            '[nullable] text -> int' => [null, 'getNullableInt', ['value' => 'a'], InvalidEnvironmentException::class],
            '[nullable] number -> int' => [42, 'getNullableInt', ['value' => '42']],
            '[nullable] zero -> int' => [0, 'getNullableInt', ['value' => '0']],
            '[nullable] empty string -> int, with default' => [null, 'getNullableInt', ['value' => '', 'default' => 1]],
            '[nullable] whitespace -> int, with default' => [null, 'getNullableInt', ['value' => ' ', 'default' => 1]],
            '[nullable] text -> int, with default' => [null, 'getNullableInt', ['value' => 'a', 'default' => 1], InvalidEnvironmentException::class],
            '[nullable] number -> int, with default' => [42, 'getNullableInt', ['value' => '42', 'default' => 1]],
            '[nullable] zero -> int, with default' => [0, 'getNullableInt', ['value' => '0', 'default' => 1]],
            '[nullable] unset -> int, no default' => [null, 'getNullableInt', [], InvalidEnvironmentException::class],
            '[nullable] unset -> int, default null' => [null, 'getNullableInt', ['default' => null]],
            '[nullable] unset -> int, default number' => [42, 'getNullableInt', ['default' => 42]],
            '[nullable] unset -> int, default zero' => [0, 'getNullableInt', ['default' => 0]],
            // getNullableBool
            '[nullable] empty string -> bool' => [null, 'getNullableBool', ['value' => '']],
            '[nullable] whitespace -> bool' => [null, 'getNullableBool', ['value' => ' ']],
            '[nullable] text -> bool' => [null, 'getNullableBool', ['value' => 'a'], InvalidEnvironmentException::class],
            '[nullable] number -> bool' => [null, 'getNullableBool', ['value' => '42'], InvalidEnvironmentException::class],
            "[nullable] '0' -> bool" => [false, 'getNullableBool', ['value' => '0']],
            "[nullable] '1' -> bool" => [true, 'getNullableBool', ['value' => '1']],
            "[nullable] 'f' -> bool" => [null, 'getNullableBool', ['value' => 'f'], InvalidEnvironmentException::class],
            "[nullable] 'false' -> bool" => [false, 'getNullableBool', ['value' => 'false']],
            "[nullable] 't' -> bool" => [null, 'getNullableBool', ['value' => 't'], InvalidEnvironmentException::class],
            "[nullable] 'true' -> bool" => [true, 'getNullableBool', ['value' => 'true']],
            "[nullable] 'n' -> bool" => [false, 'getNullableBool', ['value' => 'n']],
            "[nullable] 'no' -> bool" => [false, 'getNullableBool', ['value' => 'no']],
            "[nullable] 'y' -> bool" => [true, 'getNullableBool', ['value' => 'y']],
            "[nullable] 'yes' -> bool" => [true, 'getNullableBool', ['value' => 'yes']],
            "[nullable] 'off' -> bool" => [false, 'getNullableBool', ['value' => 'off']],
            "[nullable] 'on' -> bool" => [true, 'getNullableBool', ['value' => 'on']],
            '[nullable] empty string -> bool, with default' => [null, 'getNullableBool', ['value' => '', 'default' => true]],
            '[nullable] whitespace -> bool, with default' => [null, 'getNullableBool', ['value' => ' ', 'default' => true]],
            '[nullable] text -> bool, with default' => [null, 'getNullableBool', ['value' => 'a', 'default' => false], InvalidEnvironmentException::class],
            '[nullable] number -> bool, with default' => [null, 'getNullableBool', ['value' => '42', 'default' => false], InvalidEnvironmentException::class],
            '[nullable] zero -> bool, with default' => [false, 'getNullableBool', ['value' => '0', 'default' => true]],
            '[nullable] unset -> bool, no default' => [null, 'getNullableBool', [], InvalidEnvironmentException::class],
            '[nullable] unset -> bool, default null' => [null, 'getNullableBool', ['default' => null]],
            '[nullable] unset -> bool, default false' => [false, 'getNullableBool', ['default' => false]],
            '[nullable] unset -> bool, default true' => [true, 'getNullableBool', ['default' => true]],
        ];
    }

    public function testGetClass(): void
    {
        $_ENV[__METHOD__] = static::class;
        $this->assertSame(static::class, Env::getClass(__METHOD__, TestCase::class));

        $this->expectException(InvalidEnvironmentException::class);
        $this->expectExceptionMessage(TestCase::class . ' does not inherit ' . static::class);
        $_ENV[__METHOD__] = TestCase::class;
        Env::getClass(__METHOD__, static::class);
    }

    public function testGetClassWithUnsetVariable(): void
    {
        unset($_ENV[__METHOD__]);
        $this->assertSame(static::class, Env::getClass(__METHOD__, TestCase::class, static::class));

        $this->expectException(InvalidEnvironmentException::class);
        $this->expectExceptionMessage('Value not found in environment: ' . __METHOD__);
        Env::getClass(__METHOD__, TestCase::class);
    }
}
