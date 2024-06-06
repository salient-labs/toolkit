<?php declare(strict_types=1);

namespace Salient\Sli;

use Salient\Core\Exception\UnexpectedValueException;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Regex;
use Generator;

defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', 10007);
defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 10008);
defined('T_NAME_RELATIVE') || define('T_NAME_RELATIVE', 10009);

/**
 * Reflection for files
 */
final class TokenExtractor
{
    /** @var array<string|array{0:int,1:string,2:int}> */
    private $Tokens;

    public function __construct(string $filename)
    {
        $this->Tokens = array_values(array_filter(
            token_get_all(File::getContents($filename), \TOKEN_PARSE),
            fn($t) => !is_array($t) || !in_array($t[0], [\T_WHITESPACE])
        ));
    }

    public function dumpTokens(): void
    {
        foreach ($this->Tokens as $token) {
            if (is_array($token)) {
                printf("[%4d] %s: %s\n", $token[2], token_name($token[0]), Regex::replace('/\s+/', ' ', $token[1]));
            } else {
                printf("[%'-4s] %s\n", '', $token);
            }
        }
    }

    /**
     * Get an array that maps the file's import/alias names to qualified names
     *
     * Limitations:
     * - `namespace` boundaries are ignored
     * - processing halts at the first `class` or `trait` encountered
     * - no distinction is made between `use`, `use function` and `use const`
     *
     * @return array<string,class-string>
     */
    public function getUseMap(): array
    {
        /** @todo: Revisit this with navigable tokens */
        $map = [];
        foreach ($this->getTokensByType(\T_USE, \T_CLASS, \T_TRAIT) as $i) {
            // Ignore:
            // - `class <class> { use <trait> ...`
            // - `trait <trait> { use <trait> ...`
            // - `function() use (<variable> ...`
            if (in_array($this->Tokens[$i][0], [\T_CLASS, \T_TRAIT], true)) {
                break;
            }
            if ($this->Tokens[$i - 1] === ')') {
                continue;
            }
            $index = $i + 1;
            if (in_array($this->Tokens[$index][0] ?? null, [\T_CONST, \T_FUNCTION], true)) {
                $index++;
            }
            $this->_getUseMap($index, $map);
            unset($index);
        }

        return array_map(fn(string $import) => ltrim($import, '\\'), $map);
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

    /**
     * @param array<string,class-string> $map
     */
    private function _getUseMap(int &$index, array &$map, string $namespace = ''): void
    {
        $current = $namespace;
        $pending = true;
        do {
            $token = $this->Tokens[$index++];
            switch ($token[0] ?? $token) {
                case \T_NAME_FULLY_QUALIFIED:
                case \T_NAME_QUALIFIED:
                case \T_NAME_RELATIVE:
                case \T_NS_SEPARATOR:
                case \T_STRING:
                    $current .= $token[1];
                    break;

                case '{':
                    $this->_getUseMap($index, $map, $current);
                    $pending = false;
                    break;

                case \T_CLOSE_TAG:
                case '}':
                case ';':
                    if ($pending) {
                        $map[Get::basename($current)] = $current;
                    }
                    break 2;

                case ',':
                    if ($pending) {
                        $map[Get::basename($current)] = $current;
                    }
                    $current = $namespace;
                    $pending = true;
                    break;

                case \T_AS:
                    $token = $this->Tokens[$index++];
                    if (($token[0] ?? null) !== \T_STRING) {
                        throw new UnexpectedValueException('T_AS not followed by T_STRING');
                    }
                    $map[$token[1]] = $current;
                    $pending = false;
                    break;
            }
        } while (true);
    }
}
