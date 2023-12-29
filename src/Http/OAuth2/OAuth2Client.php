<?php declare(strict_types=1);

namespace Lkrms\Http\OAuth2;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use League\OAuth2\Client\Provider\AbstractProvider;
use Lkrms\Curler\Curler;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Http\Catalog\HttpRequestMethod as Method;
use Lkrms\Http\HttpResponse;
use Lkrms\Http\HttpServer;
use Lkrms\Http\HttpServerRequest;
use Lkrms\Store\CacheStore;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Env;
use Lkrms\Utility\Get;
use Lkrms\Utility\Json;
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
     *
     * @param array<string,mixed>|null $idToken
     * @param OAuth2GrantType::* $grantType
     */
    abstract protected function receiveToken(AccessToken $token, ?array $idToken, string $grantType): void;

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
            return $this->authorize(['scope' => $scopes], $cache);
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

        return $this->authorize($scopes ? ['scope' => $scopes] : [], $cache);
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
            OAuth2GrantType::REFRESH_TOKEN,
            ['refresh_token' => $cache->getString("{$this->TokenKey}:refresh")]
        );
    }

    /**
     * Get an access token from the OAuth 2.0 provider
     *
     * @param array<string,mixed> $options
     */
    final protected function authorize(
        array $options = [],
        CacheStore $cache = null
    ): AccessToken {
        if (isset($options['scope'])) {
            $scopes = $this->resolveScopes($options['scope']);

            // If an unexpired access or refresh token is available, extend the
            // scope of the most recently issued access token
            if (!$cache) {
                $cache = Cache::asOfNow();
            }
            if (
                $cache->has($this->TokenKey) ||
                $cache->has("{$this->TokenKey}:refresh")
            ) {
                $lastToken = $cache->getInstanceOf($this->TokenKey, AccessToken::class, 0);
                if ($lastToken) {
                    $scopes = Arr::extend($lastToken->Scopes, ...$scopes);
                }
            }

            // Always include the provider's default scopes
            $options['scope'] = Arr::extend($this->getDefaultScopes(), ...$scopes);
        }

        $this->flushTokens();

        switch ($this->Flow) {
            case OAuth2Flow::CLIENT_CREDENTIALS:
                return $this->authorizeWithClientCredentials($options);

            case OAuth2Flow::AUTHORIZATION_CODE:
                return $this->authorizeWithAuthorizationCode($options);

            default:
                throw new LogicException(sprintf('Invalid OAuth2Flow: %d', $this->Flow));
        }
    }

    /**
     * @param array<string,mixed> $options
     */
    private function authorizeWithClientCredentials(array $options = []): AccessToken
    {
        // league/oauth2-client doesn't add scopes to client_credentials
        // requests
        $scopes = null;
        if (!isset($options['scope'])) {
            $scopes = $this->getDefaultScopes() ?: null;
        } elseif (is_array($options['scope'])) {
            $scopes = $options['scope'];
        }

        if ($scopes !== null) {
            $separator = $this->getScopeSeparator();
            $options['scope'] = implode($separator, $scopes);
        }

        return $this->requestAccessToken(
            OAuth2GrantType::CLIENT_CREDENTIALS,
            $options
        );
    }

    /**
     * @param array<string,mixed> $options
     */
    private function authorizeWithAuthorizationCode(array $options = []): AccessToken
    {
        if (!$this->Listener) {
            throw new LogicException('Cannot use the Authorization Code flow without a Listener');
        }

        $url = $this->Provider->getAuthorizationUrl($options);
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
                fn(HttpServerRequest $request, bool &$continue, &$return): HttpResponse =>
                    $this->receiveAuthorizationCode($request, $continue, $return)
            );
        } finally {
            $this->Listener->stop();
        }

        if ($code === null) {
            throw new OAuth2Exception('OAuth 2.0 provider did not return an authorization code');
        }

        return $this->requestAccessToken(
            OAuth2GrantType::AUTHORIZATION_CODE,
            ['code' => $code],
            $options['scope'] ?? null
        );
    }

    /**
     * @param mixed $return
     */
    private function receiveAuthorizationCode(HttpServerRequest $request, bool &$continue, &$return): HttpResponse
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
     * @param OAuth2GrantType::* $grantType
     * @param array<string,mixed> $options
     * @param string[]|string|null $scope
     */
    private function requestAccessToken(
        string $grantType,
        array $options = [],
        $scope = null
    ): AccessToken {
        Console::debug('Requesting access token with ' . $grantType);

        $_token = $this->Provider->getAccessToken($grantType, $options);
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

        // In order of preference, get authorized scopes from:
        //
        // - The 'scope' value in the HTTP response
        // - The 'scope' claim in the access token
        // - The 'scope' sent with the request (Client Credentials flow only)
        // - The 'scope' sent with the authorization request
        // - The expired token (grant type 'refresh_token' only)
        // - The provider's default scopes
        $scopes = $this->resolveScopes($_values['scope']
            ?? $claims['scope']
            ?? $options['scope']
            ?? $scope);

        if (!$scopes && $grantType === OAuth2GrantType::REFRESH_TOKEN) {
            $cache = Cache::asOfNow();
            if ($cache->has($this->TokenKey, 0)) {
                $lastToken = $cache->getInstanceOf($this->TokenKey, AccessToken::class, 0);
                $scopes = $lastToken->Scopes;
            }
        }

        $token = new AccessToken(
            $accessToken,
            $tokenType,
            $expires,
            $scopes ?: $this->getDefaultScopes(),
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

        $this->receiveToken($token, $idToken, $grantType);

        return $token;
    }

    /**
     * Remove any tokens issued by the OAuth 2.0 provider from the cache
     *
     * @return $this
     */
    final public function flushTokens()
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

        $header = Json::parseObjectAsArray($header);
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
    final public function getIdToken(): ?array
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
    private function getDefaultScopes()
    {
        return (function () {
            /** @var AbstractProvider $this */
            // @phpstan-ignore-next-line
            return $this->getDefaultScopes();
        })->bindTo($this->Provider, $this->Provider)();
    }

    /**
     * @return string
     */
    private function getScopeSeparator()
    {
        return (function () {
            /** @var AbstractProvider $this */
            // @phpstan-ignore-next-line
            return $this->getScopeSeparator();
        })->bindTo($this->Provider, $this->Provider)();
    }

    /**
     * @param string[]|string|null $scopes
     * @return string[]
     */
    private function resolveScopes($scopes): array
    {
        if ($scopes === null) {
            return [];
        }
        if (is_string($scopes)) {
            return Arr::trim(explode(' ', $scopes));
        }
        if (!Arr::isListOfString($scopes, true)) {
            throw new InvalidArgumentException(sprintf(
                'Argument #1 ($scopes) must be of type string[]|string|null, %s given',
                Get::type($scopes),
            ));
        }
        return $scopes;
    }
}
