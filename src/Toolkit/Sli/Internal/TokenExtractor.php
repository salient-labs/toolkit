<?php declare(strict_types=1);

namespace Salient\Sli\Internal;

use Salient\Utility\Exception\ShouldNotHappenException;
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
     * Iterate over imports in the extractor
     *
     * Namespaces are ignored.
     *
     * @return iterable<string,array{\T_CLASS|\T_FUNCTION|\T_CONST,string}> An
     * iterator that maps aliases to import type and name.
     */
    public function getImports(): iterable
    {
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
            if ($token = $token->NextCode) {
                $imports = $this->doGetImports($type, $token);
                foreach ($imports as $alias => [$type, $import]) {
                    yield $alias => [$type, ltrim($import, '\\')];
                }
            }
        }
    }

    /**
     * @param \T_CLASS|\T_FUNCTION|\T_CONST $type
     * @return iterable<string,array{\T_CLASS|\T_FUNCTION|\T_CONST,string}>
     */
    private function doGetImports(int $type, NavigableToken $token, string $prefix = ''): iterable
    {
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
                    yield $token->text => [$type, $current];
                    $saved = true;
                    break;

                case \T_COMMA:
                    if (!$saved) {
                        yield Get::basename($current) => [$type, $current];
                    }
                    $current = $prefix;
                    $saved = false;
                    break;

                case \T_OPEN_BRACE:
                    /** @var NavigableToken */
                    $next = $token->NextCode;
                    /** @disregard P1006 */
                    yield from $this->doGetImports($type, $next, $current);
                    $saved = true;
                    /** @var NavigableToken */
                    $token = $token->ClosedBy;
                    break;

                case \T_CLOSE_BRACE:
                case \T_SEMICOLON:
                case \T_CLOSE_TAG:
                    if (!$saved) {
                        yield Get::basename($current) => [$type, $current];
                    }
                    return;
            }
        } while ($token = $token->Next);
    }
}
