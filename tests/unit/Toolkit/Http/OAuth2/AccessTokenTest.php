<?php declare(strict_types=1);

use Salient\Http\OAuth2\AccessToken;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Http\OAuth2\AccessToken
 */
final class AccessTokenTest extends TestCase
{
    private const TOKEN = 'eyJ0eXAiOiJKV1QiLA0KICJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJqb2UiLA0KICJleHAiOjEzMDA4MTkzODAsDQogImh0dHA6Ly9leGFtcGxlLmNvbS9pc19yb290Ijp0cnVlfQ.dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    public function testInterfaceMethods(): void
    {
        $token = new AccessToken(self::TOKEN, 'Bearer', null);
        $this->assertSame(self::TOKEN, $token->getToken());
        $this->assertSame('Bearer', $token->getTokenType());
    }

    public function testToken(): void
    {
        $token = new AccessToken(self::TOKEN, 'Bearer', null);
        $this->assertSame(self::TOKEN, $token->Token);
        $this->assertSame('Bearer', $token->Type);
    }

    public function testExpires(): void
    {
        $token = new AccessToken(self::TOKEN, 'Bearer', null);
        $this->assertNull($token->Expires);

        $expires = new DateTimeImmutable('+1 hour');
        $token = new AccessToken(self::TOKEN, 'Bearer', $expires);
        $this->assertSame($expires, $token->Expires);

        $expires = time() + 3600;
        $token = new AccessToken(self::TOKEN, 'Bearer', $expires);
        $this->assertNotNull($token->Expires);
        $this->assertSame($expires, $token->Expires->getTimestamp());
    }

    public function testInvalidExpires(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid $expires: -1');
        $token = new AccessToken(self::TOKEN, 'Bearer', -1);
    }

    public function testScopesAndClaims(): void
    {
        $scopes = ['openid', 'profile'];
        $claims = ['aud' => __CLASS__];
        $token = new AccessToken(self::TOKEN, 'Bearer', null, $scopes, $claims);
        $this->assertSame($scopes, $token->Scopes);
        $this->assertSame($claims, $token->Claims);

        $token = new AccessToken(self::TOKEN, 'Bearer', null, null, null);
        $this->assertSame([], $token->Scopes);
        $this->assertSame([], $token->Claims);
    }
}
