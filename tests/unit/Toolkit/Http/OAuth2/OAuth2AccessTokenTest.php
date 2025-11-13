<?php declare(strict_types=1);

namespace Salient\Tests\Http\OAuth2;

use Salient\Http\OAuth2\OAuth2AccessToken;
use Salient\Tests\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @covers \Salient\Http\OAuth2\OAuth2AccessToken
 * @covers \Salient\Http\GenericToken
 * @covers \Salient\Http\GenericCredential
 */
final class OAuth2AccessTokenTest extends TestCase
{
    private const TOKEN = 'eyJ0eXAiOiJKV1QiLA0KICJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJqb2UiLA0KICJleHAiOjEzMDA4MTkzODAsDQogImh0dHA6Ly9leGFtcGxlLmNvbS9pc19yb290Ijp0cnVlfQ.dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    public function testGetCredential(): void
    {
        $token = new OAuth2AccessToken(self::TOKEN);
        $this->assertSame('Bearer', $token->getAuthenticationScheme());
        $this->assertSame(self::TOKEN, $token->getCredential());
    }

    public function testGetExpires(): void
    {
        $token = new OAuth2AccessToken(self::TOKEN);
        $this->assertNull($token->getExpires());

        $expires = new DateTimeImmutable('+1 hour');
        $token = new OAuth2AccessToken(self::TOKEN, $expires);
        $this->assertSame($expires, $token->getExpires());

        $expires = time() + 3600;
        $token = new OAuth2AccessToken(self::TOKEN, $expires);
        $this->assertNotNull($token->getExpires());
        $this->assertSame($expires, $token->getExpires()->getTimestamp());
    }

    public function testInvalidExpiration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timestamp: -1');
        new OAuth2AccessToken(self::TOKEN, -1);
    }

    public function testScopesAndClaims(): void
    {
        $scopes = ['openid', 'profile'];
        $claims = ['aud' => __CLASS__];
        $token = new OAuth2AccessToken(self::TOKEN, null, $scopes, $claims);
        $this->assertSame($scopes, $token->getScopes());
        $this->assertSame($claims, $token->getClaims());

        $token = new OAuth2AccessToken(self::TOKEN);
        $this->assertSame([], $token->getScopes());
        $this->assertSame([], $token->getClaims());
    }
}
