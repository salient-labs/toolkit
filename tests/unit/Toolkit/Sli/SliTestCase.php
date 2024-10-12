<?php declare(strict_types=1);

namespace Salient\Tests\Sli;

use Salient\Sli\Internal\NavigableToken;
use Salient\Tests\TestCase;
use Salient\Utility\Get;

abstract class SliTestCase extends TestCase
{
    /**
     * @param iterable<NavigableToken> $tokens
     * @return array{array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>,string}
     */
    protected static function serializeTokens(iterable $tokens): array
    {
        foreach ($tokens as $token) {
            $actual[$token->Index] = $actualToken = [
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
            $actualCode[$token->Index] = $actualToken;
        }
        $actualCode = Get::code(
            $actualCode ?? [],
            ', ',
            ' => ',
            null,
            '    ',
            [],
            $constants ?? [],
        );
        return [$actual ?? [], $actualCode];
    }
}
