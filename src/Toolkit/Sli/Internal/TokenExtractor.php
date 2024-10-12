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
    private ?string $Namespace = null;

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
     * Get the namespace of the extractor
     *
     * Returns `null` unless the extractor was returned by
     * {@see TokenExtractor::getNamespaces()}.
     */
    public function getNamespace(): ?string
    {
        return $this->Namespace;
    }

    /**
     * Iterate over namespaces in the extractor
     *
     * @return iterable<string,static>
     */
    public function getNamespaces(): iterable
    {
        if (!$this->Tokens) {
            return;
        }

        $start = reset($this->Tokens);
        $namespace = '';
        foreach ($this->getTokens(\T_NAMESPACE) as $token) {
            // Exclude relative names
            if ($token->NextCode && $token->NextCode->id === \T_NS_SEPARATOR) {
                continue;
            }

            if ($start && $token->Prev && $start !== $token) {
                $end = $token->Prev;
                yield $namespace =>
                    $this->getRange($start, $end)->applyNamespace($namespace);
            }

            $namespace = '';
            while ($token = $token->NextCode) {
                switch ($token->id) {
                    case \T_NAME_FULLY_QUALIFIED:
                    case \T_NAME_QUALIFIED:
                    case \T_NAME_RELATIVE:
                    case \T_NS_SEPARATOR:
                    case \T_STRING:
                        $namespace .= $token->text;
                        break;

                    case \T_OPEN_BRACE:
                        yield $namespace =>
                            $this->getBlock($token)->applyNamespace($namespace);
                        /** @var NavigableToken */
                        $token = $token->ClosedBy;
                        $start = $token->Next;
                        $namespace = '';
                        break 2;

                    case \T_SEMICOLON:
                    case \T_CLOSE_TAG:
                        $start = $token->Next;
                        break 2;
                }
            }
        }

        if ($start) {
            $end = end($this->Tokens);
            yield $namespace =>
                $this->getRange($start, $end)->applyNamespace($namespace);
        }
    }

    private function applyNamespace(string $namespace): self
    {
        $this->Namespace = $namespace;
        return $this;
    }

    /**
     * Iterate over classes in the extractor
     *
     * @return iterable<string,NavigableToken> An iterator that maps `class`
     * names to `T_CLASS` tokens.
     */
    public function getClasses(): iterable
    {
        yield from $this->doGetMembers(\T_CLASS);
    }

    /**
     * Iterate over interfaces in the extractor
     *
     * @return iterable<string,NavigableToken> An iterator that maps `interface`
     * names to `T_INTERFACE` tokens.
     */
    public function getInterfaces(): iterable
    {
        yield from $this->doGetMembers(\T_INTERFACE);
    }

    /**
     * Iterate over traits in the extractor
     *
     * @return iterable<string,NavigableToken> An iterator that maps `trait`
     * names to `T_TRAIT` tokens.
     */
    public function getTraits(): iterable
    {
        yield from $this->doGetMembers(\T_TRAIT);
    }

    /**
     * Iterate over enumerations in the extractor
     *
     * @return iterable<string,NavigableToken> An iterator that maps `enum`
     * names to `T_ENUM` tokens.
     */
    public function getEnums(): iterable
    {
        yield from $this->doGetMembers(\T_ENUM);
    }

    /**
     * @return iterable<string,NavigableToken>
     */
    private function doGetMembers(int $id): iterable
    {
        foreach ($this->getTokens($id) as $token) {
            $next = $token->NextCode;
            if ($next && $next->id === \T_STRING) {
                yield $next->text => $token;
            }
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

    private function getBlock(NavigableToken $bracket): self
    {
        $clone = clone $this;
        $clone->Tokens = $bracket->getInnerTokens();
        return $clone;
    }

    private function getRange(NavigableToken $from, NavigableToken $to): self
    {
        $clone = clone $this;
        $clone->Tokens = $from->getTokens($to);
        return $clone;
    }
}
