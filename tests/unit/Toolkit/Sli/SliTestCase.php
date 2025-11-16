<?php declare(strict_types=1);

namespace Salient\Tests\Sli;

use Salient\Sli\Internal\NavigableToken;
use Salient\Tests\TestCase;
use Salient\Utility\Get;

abstract class SliTestCase extends TestCase
{
    /**
     * @param iterable<array-key,NavigableToken> $tokens
     * @param array<array{int,string,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>|null $actualCode
     * @param array<non-empty-string,string>|null $constants
     * @param-out array<array{int,string,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}> $actualCode
     * @param-out array<non-empty-string,string>|null $constants
     * @return array{array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>,string}
     */
    protected static function serializeTokens(iterable $tokens, ?array &$actualCode = null, ?array &$constants = null): array
    {
        $actualCode = [];
        foreach ($tokens as $key => $token) {
            $actual[$key] = $actualToken = [
                $token->Index,
                $token->id,
                $token->text,
                [$token->Prev->Index ?? null, $token->Next->Index ?? null],
                [$token->PrevCode->Index ?? null, $token->NextCode->Index ?? null],
                $token->Parent->Index ?? null,
                [$token->OpenedBy->Index ?? null, $token->ClosedBy->Index ?? null],
            ];
            $tokenName = $token->getTokenName();
            if ($tokenName !== null && strlen($tokenName) > 1) {
                $tokenName = '\\' . $tokenName;
                $actualToken[1] = $tokenName;
                $constants[$tokenName] = $tokenName;
            }
            $actualCode[$key] = $actualToken;
        }
        return [
            $actual ?? [],
            Get::code(
                $actualCode,
                ', ',
                ' => ',
                null,
                '    ',
                [],
                $constants ?? [],
            ),
        ];
    }
}
