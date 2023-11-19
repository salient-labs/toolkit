<?php declare(strict_types=1);

namespace Lkrms\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lkrms\Auth\Catalog\OAuth2Flow;
use Lkrms\Curler\Curler;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Store\CacheStore;
use Lkrms\Support\Catalog\HttpRequestMethod as Method;
use Lkrms\Support\Http\HttpRequest;
use Lkrms\Support\Http\HttpResponse;
use Lkrms\Support\Http\HttpServer;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Env;
use LogicException;
use Throwable;

/**
 * A headless OAuth 2.0 client that acquires and validates tokens required for
 * access to protected resources
 */
abstract class OAuth2Client
{
    protected Env $Env;

    private ?HttpServer $Listener;

    private AbstractProvider $Provider;

    /**
     * @var OAuth2Flow::*
     */
    private int $Flow;

    private string $TokenKey;

    /**
     * Return an HTTP listener to receive OAuth 2.0 redirects from the provider,
     * or null to disable flows that require it
     *
     * Reference implementation:
     *
     * ```php
     * <?php
     * class OAuth2TestClient extends OAuth2Client
     * {
     *     protected function getListener(): ?HttpServer
     *     {
     *         $listener = new HttpServer(
     *             $this->Env->get('app_host', 'localhost'),
     *             $this->Env->getInt('app_port', 27755),
     *         );
     *         $proxyHost = $this->Env->getNullable('app_proxy_host', null);
     *         $proxyPort = $this->Env->getNullableInt('app_proxy_port', null);
     *         if ($proxyHost !== null && $proxyPort !== null) {
     *             return $listener->withProxy(
     *                 $proxyHost,
     *                 $proxyPort,
     *                 $this->Env->getNullableBool('app_proxy_tls', null),
     *                 $this->Env->getNullable('app_proxy_base_path', null),
     *             );
     *         }
     *         return $listener;
     *     }
     * }
     * ```
     */
    abstract protected function getListener(): ?HttpServer;

    /**
     * Return an OAuth 2.0 provider to request and validate tokens that
     * authorize access to the resource server
     *
     * Example:
     *
     * The following provider could be used to authorize access to the Microsoft
     * Graph API on behalf of a user or application. `redirectUri` can be
     * omitted if support for the Authorization Code flow is not required.
     *
     * > The `openid`, `profile`, `email` and `offline_access` scopes are not
     * > required for access to the Graph API.
     *
     * ```php
     * <?php
     * class OAuth2TestClient extends OAuth2Client
     * {
     *     protected function getProvider(): GenericProvider
     *     {
     *         $tenantId = $this->Env->get('microsoft_graph_tenant_id');
     *         return new GenericProvider([
     *             'clientId' => $this->Env->get('microsoft_graph_app_id'),
     *             'clientSecret' => $this->Env->get('microsoft_graph_secret'),
     *             'redirectUri' => $this->getRedirectUri(),
     *             'urlAuthorize' => sprintf('https://login.microsoftonline.com/%s/oauth2/authorize', $tenantId),
     *             'urlAccessToken' => sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId),
     *             'urlResourceOwnerDetails' => sprintf('https://login.microsoftonline.com/%s/openid/userinfo', $tenantId),
     *             'scopes' => ['openid', 'profile', 'email', 'offline_access', 'https://graph.microsoft.com/.default'],
     *             'scopeSeparator' => ' ',
     *         ]);
     *     }
     * }
     * ```
     */
    abstract protected function getProvider(): AbstractProvider;

    /**
     * Return the OAuth 2.0 flow to use
     *
     * @return OAuth2Flow::*
     */
    abstract protected function getFlow(): int;

    /**
     * Return the URL of the OAuth 2.0 provider's JSON Web Key Set, or null to
     * disable JWT signature validation and decoding
     *
     * Required for token signature validation. Check the provider's
     * `https://server.com/.well-known/openid-configuration` if unsure.
     */
    abstract protected function getJsonWebKeySetUrl(): ?string;

    /**
     * Called when an access token is received from the OAuth 2.0 provider
     */
    abstract protected function receiveToken(AccessTokenInterface $token): void;

    /**
     * Creates a new OAuth2Client object
     */
    public function __construct(Env $env)
    {
        $this->Env = $env;
        $this->Listener = $this->getListener();
        $this->Provider = $this->getProvider();
        $this->Flow = $this->getFlow();
        $this->TokenKey = implode(':', [
            static::class,
            'oauth2',
            $this->Provider->getBaseAuthorizationUrl(),
            $this->Flow,
            'token',
        ]);
    }

    /**
     * Get the URI that receives redirects from the OAuth 2.0 provider
     *
     * Returns `null` if {@see OAuth2Client::getListener()} does not return an
     * HTTP listener.
     */
    final protected function getRedirectUri(): ?string
    {
        return $this->Listener
            ? sprintf('%s/oauth2/callback', $this->Listener->getBaseUrl())
            : null;
    }

    /**
     * Get an OAuth 2.0 access token from the cache if possible, otherwise use a
     * refresh token to acquire one from the provider if possible, otherwise
     * flush all tokens and authorize with the provider from scratch
     *
     * @param string[]|null $scopes
     */
    final public function getAccessToken(?array $scopes = null): AccessToken
    {
        $cache = Cache::asOfNow();
        if ($cache->has($this->TokenKey)) {
            $token = $cache->getInstanceOf($this->TokenKey, AccessToken::class);
            if ($this->accessTokenHasScopes($token, $scopes)) {
                return $token;
            }

            Console::debug('Cached token has missing scopes; re-authorizing');
            return $this->authorize();
        }

        try {
            $token = $this->refreshAccessToken($cache);
            if ($token) {
                if ($this->accessTokenHasScopes($token, $scopes)) {
                    return $token;
                }
                Console::debug('New token has missing scopes');
            }
        } catch (Throwable $ex) {
            Console::debug(
                sprintf('refresh_token failed with __%s__:', get_class($ex)),
                $ex->getMessage()
            );
        }

        return $this->authorize();
    }

    /**
     * False if one or more scopes are not present in the token
     *
     * @param string[]|null $scopes
     */
    private function accessTokenHasScopes(AccessToken $token, ?array $scopes): bool
    {
        if ($scopes && array_diff($scopes, $token->Scopes)) {
            return false;
        }
        return true;
    }

    /**
     * If an unexpired refresh token is available, use it to get a new access
     * token from the provider if possible
     */
    final protected function refreshAccessToken(CacheStore $cache): ?AccessToken
    {
        if (!$cache->has("{$this->TokenKey}:refresh")) {
            return null;
        }

        return $this->requestAccessToken(
            'refresh_token',
            ['refresh_token' => $cache->getString("{$this->TokenKey}:refresh")]
        );
    }

    /**
     * Get an access token from the OAuth 2.0 provider
     */
    final protected function authorize(): AccessToken
    {
        $this->flushTokens();

        switch ($this->Flow) {
            case OAuth2Flow::CLIENT_CREDENTIALS:
                return $this->authorizeWithClientCredentials();

            case OAuth2Flow::AUTHORIZATION_CODE:
                return $this->authorizeWithAuthorizationCode();

            default:
                throw new LogicException(sprintf('Invalid OAuth2Flow: %d', $this->Flow));
        }
    }

    private function authorizeWithClientCredentials(): AccessToken
    {
        // league/oauth2-client doesn't add scopes to client_credentials
        // requests
        $options = [];
        $scopes = $this->getDefaultScopes($this->Provider);

        if ($scopes) {
            $separator = $this->getScopeSeparator($this->Provider);
            $options['scope'] = implode($separator, $scopes);
        }

        return $this->requestAccessToken('client_credentials', $options);
    }

    private function authorizeWithAuthorizationCode(): AccessToken
    {
        if (!$this->Listener) {
            throw new LogicException('Cannot use the Authorization Code flow without a Listener');
        }

        $url = $this->Provider->getAuthorizationUrl();
        $state = $this->Provider->getState();
        Cache::set("{$this->TokenKey}:state", $state);

        Console::debug(
            'Starting HTTP server to receive authorization_code:',
            sprintf(
                '%s:%d',
                $this->Listener->Host,
                $this->Listener->Port,
            )
        );

        $this->Listener->start();
        try {
            /** @todo Call xdg-open or similar here */
            Console::log('Follow the link to authorize access:', "\n$url");
            Console::info('Waiting for authorization');
            $code = $this->Listener->listen(
                fn(HttpRequest $request, bool &$continue, &$return): HttpResponse =>
                    $this->receiveAuthorizationCode($request, $continue, $return)
            );
        } finally {
            $this->Listener->stop();
        }

        if ($code === null) {
            throw new OAuth2Exception('OAuth 2.0 provider did not return an authorization code');
        }

        return $this->requestAccessToken('authorization_code', ['code' => $code]);
    }

    /**
     * @param mixed $return
     */
    private function receiveAuthorizationCode(HttpRequest $request, bool &$continue, &$return): HttpResponse
    {
        $url = parse_url($request->Target);
        $path = $url['path'] ?? null;
        if (
            $request->Method !== Method::GET ||
            $path !== '/oauth2/callback'
        ) {
            $continue = true;
            return new HttpResponse('Invalid request.', 400, 'Bad Request');
        }

        $state = Cache::getString("{$this->TokenKey}:state");
        Cache::delete("{$this->TokenKey}:state");
        parse_str($url['query'] ?? '', $fields);
        $code = $fields['code'] ?? null;

        if (
            $state !== null &&
            $state === ($fields['state'] ?? null) &&
            $code !== null
        ) {
            Console::debug('Authorization code received and verified');
            $return = $code;
            return new HttpResponse('Authorization received. You may now close this window.');
        }

        Console::debug('Request did not provide a valid authorization code');
        return new HttpResponse('Invalid request. Please try again.', 400, 'Bad Request');
    }

    /**
     * Request an access token from the OAuth 2.0 provider, then validate, cache
     * and return it
     *
     * @param array<string,mixed> $parameters
     */
    private function requestAccessToken(string $grant, array $parameters = []): AccessToken
    {
        Console::debug('Requesting access token with ' . $grant);

        $_token = $this->Provider->getAccessToken($grant, $parameters);
        $_values = $_token->getValues();

        $tokenType = $_values['token_type'] ?? null;
        if ($tokenType === null) {
            throw new OAuth2Exception('OAuth 2.0 provider did not return a token type');
        }

        $accessToken = $_token->getToken();
        $refreshToken = $_token->getRefreshToken();
        $idToken = $_values['id_token'] ?? null;
        $claims = $this->getValidJsonWebToken($accessToken) ?: [];

        // OAuth 2.0 clients shouldn't inspect tokens they aren't party to, but
        // some providers rely on non-compliant behaviour, e.g. Xero surfaces
        // `authentication_event_id` as an access token claim
        $expires = $_token->getExpires();
        if ($expires === null) {
            $expires = $claims['exp'] ?? null;
        }
        $scope = $_values['scope'] ?? $claims['scope'] ?? null;

        if (is_string($scope)) {
            $scopes = Arr::trim(explode(' ', $scope));
        } elseif (Arr::listOfString($scope)) {
            $scopes = $scope;
        }

        $token = new AccessToken(
            $accessToken,
            $tokenType,
            $expires,
            $scopes ?? $this->getDefaultScopes($this->Provider),
            $claims
        );

        Cache::set($this->TokenKey, $token, $token->Expires);

        if ($idToken !== null) {
            $idToken = $this->getValidJsonWebToken($idToken, true);
            // Keep the ID token until access and/or refresh tokens expire
            Cache::set("{$this->TokenKey}:id", $idToken);
        }

        if ($refreshToken !== null) {
            Cache::set("{$this->TokenKey}:refresh", $refreshToken);
        }

        $this->receiveToken($_token);

        return $token;
    }

    /**
     * Remove any tokens issued by the OAuth 2.0 provider from the cache
     *
     * @return $this
     */
    final protected function flushTokens()
    {
        Console::debug('Flushing cached tokens');
        Cache::delete($this->TokenKey);
        Cache::delete("{$this->TokenKey}:id");
        Cache::delete("{$this->TokenKey}:refresh");
        Cache::delete("{$this->TokenKey}:state");

        return $this;
    }

    /**
     * Validate and decode a token issued by the OAuth 2.0 provider
     *
     * The provider's JSON Web Key Set (JWKS) is required for signature
     * validation, so {@see OAuth2Client::getJsonWebKeySetUrl()} must return a
     * URL.
     *
     * If JWT signature validation fails, an exception is thrown if `$required`
     * is `true`, otherwise `null` is returned to the caller.
     *
     * Access tokens should be presented to resource servers even if they can't
     * be validated. Technically, the only party inspecting an OAuth 2.0 token
     * should be its intended "aud"ience, and in some cases (e.g. when the
     * Microsoft Identity Platform issues an access token for the Microsoft
     * Graph API), token signatures are deliberately broken to discourage
     * inspection (e.g. by adding a nonce to the header).
     *
     * @return array<string,mixed>
     */
    private function getValidJsonWebToken(string $token, bool $required = false, bool $refreshKeys = false, string $alg = null): ?array
    {
        $jwks = $this->getJsonWebKeySet($refreshKeys);
        if ($jwks === null) {
            return null;
        }

        // If there are any keys with no "alg"orithm (hello, Microsoft Identity
        // Platform), `JWK::parseKeySet()` fails, so extract "alg" from the
        // token and pass it to `JWK::parseKeySet()`
        if ($alg === null && $this->jwksHasKeyWithNoAlgorithm($jwks)) {
            $alg = $this->getTokenAlgorithm($token);
        }

        try {
            return (array) JWT::decode(
                $token,
                JWK::parseKeySet($jwks, $alg)
            );
        } catch (SignatureInvalidException $ex) {
            // Refresh the JWKS when signature validation fails
            if (!$refreshKeys) {
                return $this->getValidJsonWebToken($token, $required, true, $alg);
            }
            if ($required) {
                throw $ex;
            }
            return null;
        }
    }

    /**
     * @param array{keys:array<array<string,string[]|string>>} $jwks
     */
    private function jwksHasKeyWithNoAlgorithm(array $jwks): bool
    {
        foreach ($jwks['keys'] as $key) {
            if (!isset($key['alg'])) {
                return true;
            }
        }
        return false;
    }

    private function getTokenAlgorithm(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $header = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if ($header === false) {
            return null;
        }

        $header = json_decode($header, true);
        return $header['alg'] ?? null;
    }

    /**
     * @return array{keys:array<array<string,string[]|string>>}|null
     */
    private function getJsonWebKeySet(bool $refresh = false): ?array
    {
        $url = $this->getJsonWebKeySetUrl();
        if ($url === null) {
            return null;
        }

        return Curler::build()
            ->baseUrl($url)
            ->expiry(0)
            ->flush($refresh)
            ->get();
    }

    /**
     * Get the decoded ID token most recently issued with an access token by the
     * OAuth 2.0 provider
     *
     * @return array<string,mixed>|null
     */
    final protected function getIdToken(): ?array
    {
        // Don't [re-]authorize for an ID token that won't be issued
        if (
            Cache::has($this->TokenKey, 0) &&
            !Cache::has("{$this->TokenKey}:id", 0)
        ) {
            return null;
        }

        // Don't return stale identity information
        $cache = Cache::asOfNow();
        if (!$cache->has($this->TokenKey)) {
            $this->getAccessToken();
            $cache = Cache::asOfNow();
        }

        return $cache->has("{$this->TokenKey}:id")
            ? $cache->get("{$this->TokenKey}:id")
            : null;
    }

    /**
     * @return string[]
     */
    private function getDefaultScopes(AbstractProvider $provider)
    {
        return (function () {
            /** @var AbstractProvider $this */
            // @phpstan-ignore-next-line
            return $this->getDefaultScopes();
        })->bindTo($provider, $provider)();
    }

    /**
     * @return string
     */
    private function getScopeSeparator(AbstractProvider $provider)
    {
        return (function () {
            /** @var AbstractProvider $this */
            // @phpstan-ignore-next-line
            return $this->getScopeSeparator();
        })->bindTo($provider, $provider)();
    }
}
