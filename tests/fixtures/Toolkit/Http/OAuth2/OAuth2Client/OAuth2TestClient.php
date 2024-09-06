<?php declare(strict_types=1);

namespace Salient\Tests\Http\OAuth2\OAuth2Client;

use League\OAuth2\Client\Provider\GenericProvider;
use Salient\Core\Facade\Console;
use Salient\Http\OAuth2\AccessToken;
use Salient\Http\OAuth2\OAuth2Client;
use Salient\Http\OAuth2\OAuth2Flow;
use Salient\Http\HttpServer;
use Salient\Utility\Env;

final class OAuth2TestClient extends OAuth2Client
{
    private string $TenantId;
    private string $AppId;
    private string $Secret;

    public function __construct(string $tenantId, string $appId, string $secret)
    {
        $this->TenantId = $tenantId;
        $this->AppId = $appId;
        $this->Secret = $secret;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function getListener(): ?HttpServer
    {
        $listener = new HttpServer(
            Env::get('app_host', 'localhost'),
            Env::getInt('app_port', 27755),
        );

        $proxyHost = Env::getNullable('app_proxy_host', null);
        $proxyPort = Env::getNullableInt('app_proxy_port', null);

        if ($proxyHost !== null && $proxyPort !== null) {
            return $listener->withProxy(
                $proxyHost,
                $proxyPort,
                Env::getNullableBool('app_proxy_tls', null),
                Env::get('app_proxy_base_path', ''),
            );
        }

        return $listener;
    }

    /**
     * @inheritDoc
     */
    protected function getProvider(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $this->AppId,
            'clientSecret' => $this->Secret,
            'redirectUri' => $this->getRedirectUri(),
            'urlAuthorize' => sprintf('https://login.microsoftonline.com/%s/oauth2/authorize', $this->TenantId),
            'urlAccessToken' => sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $this->TenantId),
            'urlResourceOwnerDetails' => sprintf('https://login.microsoftonline.com/%s/openid/userinfo', $this->TenantId),
            // The only scope required for access to the Microsoft Graph API is
            // https://graph.microsoft.com/.default
            'scopes' => ['openid', 'profile', 'email', 'offline_access', 'https://graph.microsoft.com/.default'],
            'scopeSeparator' => ' ',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function getFlow(): int
    {
        return OAuth2Flow::CLIENT_CREDENTIALS;
    }

    /**
     * @inheritDoc
     */
    protected function getJsonWebKeySetUrl(): ?string
    {
        return 'https://login.microsoftonline.com/common/discovery/keys';
    }

    /**
     * @inheritDoc
     */
    protected function receiveToken(AccessToken $token, ?array $idToken, string $grantType): void
    {
        Console::debug('OAuth 2.0 access token received');
    }
}
