<?php declare(strict_types=1);

namespace Salient\Sli\Internal;

use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Get;

/**
 * @internal
 */
final class TokenExtractor
{
    /** @var NavigableToken[] */
    private array $Tokens = [];

    public function __construct(string $code)
    {
        $this->Tokens = NavigableToken::tokenize($code, \TOKEN_PARSE, true);
    }

    public static function fromFile(string $filename): self
    {
        return new self(File::getContents($filename));
    }

    /**
     * Iterate over tokens in the extractor, optionally filtering them by ID
     *
     * @return iterable<int,NavigableToken>
     */
    public function getTokens(int ...$id): iterable
    {
        if ($id) {
            $idx = array_fill_keys($id, true);
            foreach ($this->Tokens as $index => $token) {
                if ($idx[$token->id] ?? false) {
                    yield $index => $token;
                }
            }
        } else {
            yield from $this->Tokens;
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
     * @return array<string,array{\T_CLASS|\T_FUNCTION|\T_CONST,string}>
     */
    public function getUseMap(): array
    {
        $map = [];

        foreach ($this->getTokens(\T_USE) as $token) {
            // Exclude `function () use (<variable>) {}`
            if ($token->PrevCode && $token->PrevCode->id === \T_CLOSE_PARENTHESIS) {
                continue;
            }
            // Exclude `class <name> { use <trait>; }`
            if ($token->Parent && (
                $token->Parent->id !== \T_OPEN_BRACE
                || !$token->Parent->PrevCode
                || !$token->Parent->PrevCode->isDeclarationOf(\T_NAMESPACE)
            )) {
                continue;
            }
            // Detect `use function` and `use const`
            if ($token->NextCode && (
                $token->NextCode->id === \T_FUNCTION
                || $token->NextCode->id === \T_CONST
            )) {
                $type = $token->NextCode->id;
                $token = $token->NextCode;
            } else {
                $type = \T_CLASS;
            }
            $token = $token->NextCode;
            if (!$token) {
                continue;
            }
            $map = $this->doGetUseMap($type, $token) + $map;
        }

        // @phpstan-ignore return.type
        return array_map(fn($import) => Arr::set($import, 1, ltrim($import[1], '\\')), $map);
    }

    /**
     * @param \T_CLASS|\T_FUNCTION|\T_CONST $type
     * @return array<string,array{\T_CLASS|\T_FUNCTION|\T_CONST,string}>
     */
    private function doGetUseMap(int $type, NavigableToken $token, string $prefix = ''): array
    {
        $map = [];
        $current = $prefix;
        $saved = false;
        do {
            switch ($token->id) {
                case \T_NAME_FULLY_QUALIFIED:
                case \T_NAME_QUALIFIED:
                case \T_NAME_RELATIVE:
                case \T_NS_SEPARATOR:
                case \T_STRING:
                    $current .= $token->text;
                    break;

                case \T_AS:
                    /** @var NavigableToken */
                    $token = $token->NextCode;
                    if ($token->id !== \T_STRING) {
                        // @codeCoverageIgnoreStart
                        throw new ShouldNotHappenException('T_AS not followed by T_STRING');
                        // @codeCoverageIgnoreEnd
                    }
                    $map[$token->text] = [$type, $current];
                    $saved = true;
                    break;

                case \T_COMMA:
                    if (!$saved) {
                        $map[Get::basename($current)] = [$type, $current];
                    }
                    $current = $prefix;
                    $saved = false;
                    break;

                case \T_OPEN_BRACE:
                    /** @var NavigableToken */
                    $next = $token->NextCode;
                    /** @disregard P1006 */
                    $map = $this->doGetUseMap($type, $next, $current) + $map;
                    $saved = true;
                    /** @var NavigableToken */
                    $token = $token->ClosedBy;
                    break;

                case \T_CLOSE_BRACE:
                case \T_SEMICOLON:
                case \T_CLOSE_TAG:
                    if (!$saved) {
                        $map[Get::basename($current)] = [$type, $current];
                    }
                    break 2;
            }
        } while ($token = $token->Next);

        return $map;
    }
}
