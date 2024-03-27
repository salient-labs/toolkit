<?php declare(strict_types=1);

namespace Salient\Tests\Http\OAuth2\OAuth2Client;

use League\OAuth2\Client\Provider\GenericProvider;
use Salient\Core\Utility\Env;
use Salient\Http\OAuth2\AccessToken;
use Salient\Http\OAuth2\OAuth2Client;
use Salient\Http\OAuth2\OAuth2Flow;
use Salient\Http\HttpServer;

class OAuth2TestClient extends OAuth2Client
{
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

    protected function getProvider(): GenericProvider
    {
        $tenantId = Env::get('microsoft_graph_tenant_id');

        return new GenericProvider([
            'clientId' => Env::get('microsoft_graph_app_id'),
            'clientSecret' => Env::get('microsoft_graph_secret'),
            'redirectUri' => $this->getRedirectUri(),
            'urlAuthorize' => sprintf('https://login.microsoftonline.com/%s/oauth2/authorize', $tenantId),
            'urlAccessToken' => sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId),
            'urlResourceOwnerDetails' => sprintf('https://login.microsoftonline.com/%s/openid/userinfo', $tenantId),
            'scopes' => ['openid', 'profile', 'email', 'offline_access', 'https://graph.microsoft.com/.default'],
            'scopeSeparator' => ' ',
        ]);
    }

    protected function getFlow(): int
    {
        return OAuth2Flow::CLIENT_CREDENTIALS;
    }

    protected function getJsonWebKeySetUrl(): ?string
    {
        return 'https://login.microsoftonline.com/common/discovery/keys';
    }

    protected function receiveToken(AccessToken $token, ?array $idToken, string $grantType): void {}
}
