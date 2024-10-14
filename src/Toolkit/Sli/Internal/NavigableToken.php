<?php declare(strict_types=1);

namespace Salient\Sli\Internal;

use LogicException;

/**
 * @internal
 */
class NavigableToken extends GenericToken
{
    private const OPEN_BRACKET = [
        \T_OPEN_BRACE => true,
        \T_OPEN_BRACKET => true,
        \T_OPEN_PARENTHESIS => true,
        \T_ATTRIBUTE => true,
        \T_CURLY_OPEN => true,
        \T_DOLLAR_OPEN_CURLY_BRACES => true,
    ];

    private const CLOSE_BRACKET = [
        \T_CLOSE_BRACE => true,
        \T_CLOSE_BRACKET => true,
        \T_CLOSE_PARENTHESIS => true,
    ];

    private const NOT_CODE = [
        \T_OPEN_TAG => true,
        \T_OPEN_TAG_WITH_ECHO => true,
        \T_CLOSE_TAG => true,
        \T_COMMENT => true,
        \T_DOC_COMMENT => true,
        \T_INLINE_HTML => true,
        \T_WHITESPACE => true,
    ];

    private const NAME = [
        \T_NAME_FULLY_QUALIFIED => true,
        \T_NAME_QUALIFIED => true,
        \T_NAME_RELATIVE => true,
        \T_NS_SEPARATOR => true,
        \T_STRING => true,
    ];

    private const DECLARATION_PART = [
        \T_ABSTRACT => true,
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => true,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => true,
        \T_AND => true,
        \T_ATTRIBUTE => true,
        \T_CASE => true,
        \T_CLASS => true,
        \T_COMMA => true,
        \T_CONST => true,
        \T_DECLARE => true,
        \T_ENUM => true,
        \T_EXTENDS => true,
        \T_FINAL => true,
        \T_FUNCTION => true,
        \T_IMPLEMENTS => true,
        \T_INTERFACE => true,
        \T_NAME_FULLY_QUALIFIED => true,
        \T_NAME_QUALIFIED => true,
        \T_NAME_RELATIVE => true,
        \T_NAMESPACE => true,
        \T_NS_SEPARATOR => true,
        \T_PRIVATE => true,
        \T_PROTECTED => true,
        \T_PUBLIC => true,
        \T_READONLY => true,
        \T_STATIC => true,
        \T_STRING => true,
        \T_TRAIT => true,
        \T_USE => true,
        \T_VAR => true,
    ];

    public int $Index = -1;
    /** @var static|null */
    public ?self $Prev = null;
    /** @var static|null */
    public ?self $Next = null;
    /** @var static|null */
    public ?self $PrevCode = null;
    /** @var static|null */
    public ?self $NextCode = null;
    /** @var static|null */
    public ?self $Parent = null;
    /** @var static|null */
    public ?self $OpenedBy = null;
    /** @var static|null */
    public ?self $ClosedBy = null;

    /**
     * @inheritDoc
     */
    public static function tokenize(string $code, int $flags = 0, bool $discardWhitespace = false): array
    {
        /** @var static|null */
        $prev = null;
        $nextIndex = 0;
        foreach (parent::tokenize($code, $flags) as $token) {
            if ($discardWhitespace && (
                $token->id === \T_WHITESPACE
                || $token->id === \T_BAD_CHARACTER
            )) {
                continue;
            }

            $token->Index = $nextIndex++;
            $tokens[] = $token;

            if ($prev) {
                $token->Prev = $prev;
                $prev->Next = $token;

                if (self::NOT_CODE[$prev->id] ?? false) {
                    $token->PrevCode = $prev->PrevCode;
                } else {
                    $token->PrevCode = $prev;
                }

                if (self::NOT_CODE[$token->id] ?? false) {
                    $token->NextCode = &$prev->NextCode;
                } else {
                    $prev->NextCode = $token;
                }
            }

            if (!($flags & \TOKEN_PARSE) || !$prev) {
                $prev = $token;
                continue;
            }

            if (self::OPEN_BRACKET[$prev->id] ?? false) {
                $token->Parent = $prev;
            } else {
                $token->Parent = $prev->Parent;
            }

            if (self::CLOSE_BRACKET[$token->id] ?? false) {
                /** @var static */
                $openedBy = $token->Parent;
                $token->OpenedBy = $openedBy;
                $openedBy->ClosedBy = $token;
                $token->Parent = $openedBy->Parent;
            }

            $prev = $token;
        }

        return $tokens ?? [];
    }

    /**
     * Get tokens enclosed by the token
     *
     * @return static[]
     */
    public function getInnerTokens(): array
    {
        $token = $this->OpenedBy ?? $this;

        if (!$token->ClosedBy || $token->Next === $token->ClosedBy) {
            return [];
        }

        /** @var static */
        $from = $token->Next;
        /** @var static */
        $to = $token->ClosedBy->Prev;

        return $from->getTokens($to);
    }

    /**
     * Get the token and its following tokens up to and including the given
     * token
     *
     * @param static $to
     * @return static[]
     */
    public function getTokens(self $to): array
    {
        if ($this->Index > $to->Index) {
            return [];
        }

        $token = $this;
        do {
            $tokens[$token->Index] = $token;
            if ($token === $to) {
                return $tokens;
            }
        } while ($token = $token->Next);

        // @codeCoverageIgnoreStart
        throw new LogicException('Tokens must belong to the same document');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get an identifier from a token and its following tokens, optionally
     * assigning the next code token to a variable
     *
     * If the token is not a name token (`T_NAME_*`, `T_NS_SEPARATOR` or
     * `T_STRING`), an empty string is returned and the token itself is assigned
     * to `$next`.
     *
     * @phpstan-param static|null $next
     * @param-out static|null $next
     */
    public function getName(?self &$next = null): string
    {
        $name = '';
        $token = $this;
        do {
            if ((self::NAME[$token->id] ?? false) || (
                $token->id === \T_NAMESPACE
                && $token->NextCode
                && $token->NextCode->id === \T_NS_SEPARATOR
            )) {
                $name .= $token->text;
                continue;
            }
            break;
        } while ($token = $token->NextCode);

        $next = $token;
        return $name;
    }

    /**
     * Check if the token, together with previous tokens, forms a declaration of
     * the given type
     */
    public function isDeclarationOf(int $id): bool
    {
        $token = $this;
        do {
            if (!(self::DECLARATION_PART[$token->id] ?? false)) {
                return false;
            }
            if ($token->id === $id) {
                return true;
            }
        } while ($token = $token->PrevCode);

        return false;
    }
}
