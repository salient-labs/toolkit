<?php declare(strict_types=1);

namespace Lkrms\Support;

use Generator;
use Lkrms\Facade\Convert;
use UnexpectedValueException;

defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 10001);

/**
 * Reflection for files
 *
 */
final class TokenExtractor
{
    /**
     * @var array<string|array>
     */
    private $Tokens;

    public function __construct(string $filename)
    {
        $this->Tokens = array_values(array_filter(
            token_get_all(file_get_contents($filename), TOKEN_PARSE),
            fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE])
        ));
    }

    /**
     * @return Generator<int>
     */
    private function getTokensByType(int ...$id): Generator
    {
        for ($i = 0; $i < count($this->Tokens); $i++) {
            $token = $this->Tokens[$i];
            if (!is_array($token) || !in_array($token[0], $id)) {
                continue;
            }

            yield $i;
        }
    }

    public function dumpTokens(): void
    {
        foreach ($this->Tokens as $token) {
            if (is_array($token)) {
                printf("[%4d] %s: %s\n", $token[2], token_name($token[0]), preg_replace('/\s+/', ' ', $token[1]));
            } else {
                printf("[%'-4s] %s\n", '', $token);
            }
        }
    }

    private function _getUseMap(int &$index, array &$map, string $namespace = '')
    {
        $current = $namespace;
        $pending = true;
        do {
            $token = $this->Tokens[$index++];
            switch ($token[0] ?? $token) {
                case T_NAME_QUALIFIED:
                case T_STRING:
                case T_NS_SEPARATOR:
                    $current .= $token[1];
                    break;

                case '{':
                    $this->_getUseMap($index, $map, $current);
                    $pending = false;
                    break;

                case '}':
                case ';':
                    if ($pending) {
                        $map[Convert::classToBasename($current)] = $current;
                    }
                    break 2;

                case ',':
                    if ($pending) {
                        $map[Convert::classToBasename($current)] = $current;
                    }
                    $current = $namespace;
                    $pending = true;
                    break;

                case T_AS:
                    $token = $this->Tokens[$index++];
                    if (($token[0] ?? null) !== T_STRING) {
                        throw new UnexpectedValueException('T_AS not followed by T_STRING');
                    }
                    $map[$token[1]] = $current;
                    $pending = false;
                    break;
            }
        } while (true);
    }

    public function getUseMap(): array
    {
        $map = [];
        foreach ($this->getTokensByType(T_USE) as $i) {
            // Ignore:
            // - `class <class> { use <trait> ...`
            // - `function() use (<variable> ...`
            if (in_array($this->Tokens[$i - 1] ?? null, ['{', ')'])) {
                continue;
            }
            $index = $i + 1;
            $this->_getUseMap($index, $map);
            unset($index);
        }

        return $map;
    }
}
