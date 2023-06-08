<?php declare(strict_types=1);

namespace Lkrms\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lkrms\Auth\Catalog\OAuth2Flow;
use Lkrms\Concern\TReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Support\Catalog\HttpRequestMethod;
use Lkrms\Support\Http\HttpRequest;
use Lkrms\Support\Http\HttpResponse;
use Lkrms\Support\Http\HttpServer;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * An OAuth 2.0 client that acquires and validates tokens required for access to
 * endpoints on a resource server
 *
 * To use this trait in a class:
 *
 * 1. Implement the {@see IReadable} interface
 * 2. Insert {@see OAuth2Client} and implement its abstract methods
 * 3. Use {@see OAuth2Client::getAccessToken()} to acquire a token at the last
 *    possible moment before it's needed
 *
 * @property-read ?HttpServer $OAuth2Listener
 * @property-read ?string $OAuth2RedirectUri
 * @property-read OAuth2Provider $OAuth2Provider
 * @property-read string $OAuth2TokenKey
 *
 * @see IReadable Must be implemented by classes that use this trait.
 *
 * @psalm-require-implements IReadable
 */
trait OAuth2Client
{
    use TReadable;

    /**
     * Return an HTTP listener to receive OAuth 2.0 redirects from the provider,
     * or null to disable flows that require it
     *
     * Reference implementation:
     *
     * ```php
     * protected function getOAuth2Listener(): ?\Lkrms\Support\Http\HttpServer
     * {
     *     $listener = new \Lkrms\Support\Http\HttpServer(
     *         $this->Env->get('app_host', 'localhost'),
     *         $this->Env->getInt('app_port', 27755)
     *     );
     *     $proxyHost = $this->Env->get('app_proxy_host', null);
     *     $proxyPort = $this->Env->getInt('app_proxy_port', null);
     *     if ($proxyHost && $proxyPort !== null) {
     *         return $listener->withProxy(
     *             $proxyHost,
     *             $proxyPort,
     *             $this->Env->getBool('app_proxy_tls', null)
     *         );
     *     }
     *     return $listener;
     * }
     * ```
     *
     */
    abstract protected function getOAuth2Listener(): ?HttpServer;

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
     * protected function getOAuth2Provider(): \Lkrms\Auth\OAuth2Provider
     * {
     *     return new \Lkrms\Auth\OAuth2Provider([
     *         'clientId'                => $this->ClientId,
     *         'clientSecret'            => $this->ClientSecret,
     *         'redirectUri'             => $this->OAuth2RedirectUri,
     *         'urlAuthorize'            => sprintf('https://login.microsoftonline.com/%s/oauth2/authorize', $this->TenantId),
     *         'urlAccessToken'          => sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $this->TenantId),
     *         'urlResourceOwnerDetails' => sprintf('https://login.microsoftonline.com/%s/openid/userinfo', $this->TenantId),
     *         'scopes'                  => ['openid', 'profile', 'email', 'offline_access', 'https://graph.microsoft.com/.default'],
     *         'scopeSeparator'          => ' ',
     *     ]);
     * }
     * ```
     *
     */
    abstract protected function getOAuth2Provider(): OAuth2Provider;

    /**
     * Return the OAuth 2.0 flow to use
     *
     * @return OAuth2Flow::*
     */
    abstract protected function getOAuth2Flow(): int;

    /**
     * Return the URL of the OAuth 2.0 provider's JSON Web Key Set, or null to
     * disable JWT signature validation and decoding
     *
     * Required for token signature validation. Check the provider's
     * `https://server.com/.well-known/openid-configuration` if unsure.
     *
     */
    abstract protected function getOAuth2JsonWebKeySetUrl(): ?string;

    /**
     * Called when an access token is received from the OAuth 2.0 provider
     *
     */
    abstract protected function receiveOAuth2Token(AccessTokenInterface $token): void;

    /**
     * @var HttpServer|false|null
     */
    private $_OAuth2Listener;

    /**
     * @var string|false|null
     */
    private $_OAuth2RedirectUri;

    /**
     * @var OAuth2Provider|null
     */
    private $_OAuth2Provider;

    /**
     * @var string|null
     */
    private $_OAuth2TokenKey;

    /**
     * @internal
     */
    final protected function _getOAuth2Listener(): ?HttpServer
    {
        return $this->_OAuth2Listener !== null
            ? ($this->_OAuth2Listener ?: null)
            : (($this->_OAuth2Listener = $this->getOAuth2Listener() ?: false) ?: null);
    }

    /**
     * @internal
     */
    final protected function _getOAuth2RedirectUri(): ?string
    {
        return $this->_OAuth2RedirectUri !== null
            ? ($this->_OAuth2RedirectUri ?: null)
            : (($this->_OAuth2RedirectUri = $this->OAuth2Listener
                ? sprintf('%s/oauth2/callback', $this->OAuth2Listener->getBaseUrl())
                : false) ?: null);
    }

    /**
     * @internal
     */
    final protected function _getOAuth2Provider(): OAuth2Provider
    {
        return $this->_OAuth2Provider
            ?: ($this->_OAuth2Provider = $this->getOAuth2Provider());
    }

    /**
     * @internal
     */
    final protected function _getOAuth2TokenKey(?string $type = null): string
    {
        return ($this->_OAuth2TokenKey
            ?: ($this->_OAuth2TokenKey =
                implode(':', [
                    static::class,
                    'oauth2',
                    $this->OAuth2Provider->getBaseAuthorizationUrl(),
                    $this->getOAuth2Flow(),
                    'token',
                ]))) . ($type ? ":$type" : '');
    }

    /**
     * True if an unexpired OAuth 2.0 access token is available
     *
     * If scopes are given and one or more are not granted, `false` is returned
     * but `$token` is still set.
     *
     * @param string[]|null $scopes
     */
    final protected function hasAccessToken(?AccessToken &$token = null, ?array $scopes = null): bool
    {
        /** @var AccessToken|false $_token */
        if (($_token = Cache::get($this->OAuth2TokenKey)) === false) {
            return false;
        }
        $token = $_token;

        return $this->checkAccessTokenScopes($token, $scopes);
    }

    /**
     * Get an OAuth 2.0 access token from the cache if possible, otherwise use a
     * refresh token to acquire one from the provider, or if that's not
     * possible, flush all tokens and authorize with the provider from scratch
     *
     * This may be the only {@see OAuth2Client} method inheritors need to call.
     *
     */
    final protected function getAccessToken(?array $scopes = null): AccessToken
    {
        if ($this->hasAccessToken($token, $scopes)) {
            return $token;
        }

        // Don't refresh tokens with missing scopes
        if ($token ?? null) {
            Console::debug('Cached token has missing scopes');
        } else {
            try {
                if ($this->refreshAccessToken($token)) {
                    if ($this->checkAccessTokenScopes($token, $scopes)) {
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
        }

        return $this->authorize();
    }

    /**
     * False if one or more scopes are not granted
     *
     */
    private function checkAccessTokenScopes(AccessToken $token, ?array $scopes): bool
    {
        return !$scopes ||
            !array_diff($scopes, (array) ($token->Scopes ?: $token->Claims['scope'] ?? []));
    }

    /**
     * If an unexpired OAuth 2.0 refresh token is available, use it to get a new
     * access token from the provider if possible
     *
     */
    final protected function refreshAccessToken(?AccessToken &$token = null): bool
    {
        $refreshToken = Cache::get("{$this->OAuth2TokenKey}:refresh");
        if ($refreshToken === false) {
            return false;
        }
        $token = $this->requestAccessToken('refresh_token', ['refresh_token' => $refreshToken]);

        return true;
    }

    /**
     * Get an access token from the OAuth 2.0 provider
     *
     */
    final protected function authorize(): AccessToken
    {
        $this->flushAccessToken();

        switch ($flow = $this->getOAuth2Flow()) {
            case OAuth2Flow::CLIENT_CREDENTIALS:
                // league/oauth2-client doesn't add scopes to client_credentials
                // requests, but if scopes have been provided, it's not like
                // there's anywhere else to request them
                $options = [];
                if ($scopes = $this->OAuth2Provider->getDefaultScopes()) {
                    $options['scope'] = implode($this->OAuth2Provider->getScopeSeparator(), $scopes);
                }
                return $this->requestAccessToken('client_credentials', $options);

            case OAuth2Flow::AUTHORIZATION_CODE:
                break;

            default:
                throw new LogicException(sprintf('Invalid OAuth2Flow: %d', $flow));
        }

        if (!$this->OAuth2Listener) {
            throw new LogicException('Cannot use the Authorization Code flow without an OAuth2Listener');
        }

        $url = $this->OAuth2Provider->getAuthorizationUrl();
        $state = $this->OAuth2Provider->getState();
        Cache::set("{$this->OAuth2TokenKey}:state", $state);

        Console::debug(
            'Starting HTTP server to receive authorization_code:',
            sprintf(
                '%s:%d',
                $this->OAuth2Listener->Host,
                $this->OAuth2Listener->Port
            )
        );

        $this->OAuth2Listener->start();
        try {
            /** @todo Call xdg-open or similar here */
            Console::log('Follow the link to authorize access:', "\n$url");
            Console::info('Waiting for authorization');
            $code = $this->OAuth2Listener->listen(
                function (HttpRequest $request, bool &$continue, &$return): HttpResponse {
                    if ($request->Method !== HttpRequestMethod::GET ||
                            ($url = parse_url($request->Target)) === false ||
                            ($url['path'] ?? null) !== '/oauth2/callback') {
                        $continue = true;

                        return new HttpResponse('Invalid request.', 400, 'Bad Request');
                    }

                    $state = Cache::get("{$this->OAuth2TokenKey}:state");
                    Cache::delete("{$this->OAuth2TokenKey}:state");
                    parse_str($url['query'] ?? '', $fields);

                    if ($state && ($fields['state'] ?? null) === $state &&
                            ($code = $fields['code'] ?? null)) {
                        Console::debug('Authorization code received and validated');
                        $return = $code;

                        return new HttpResponse('Authorization received. You may now close this window.');
                    }

                    Console::debug('Request did not provide a valid authorization code');

                    return new HttpResponse('Invalid request. Please try again.', 400, 'Bad Request');
                }
            );
        } finally {
            $this->OAuth2Listener->stop();
        }

        if (!$code) {
            throw new RuntimeException('OAuth 2.0 provider did not return an authorization code');
        }

        return $this->requestAccessToken('authorization_code', ['code' => $code]);
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

        $_token = $this->OAuth2Provider->getAccessToken($grant, $parameters);
        $_values = $_token->getValues();
        $accessToken = $_token->getToken();
        $claims = $this->getValidJsonWebToken($accessToken) ?: [];
        $expires = $_token->getExpires() ?: $claims['exp'] ?? null;

        if (($tokenType = $_values['token_type'] ?? null) === null) {
            throw new RuntimeException('OAuth 2.0 provider did not return a token type');
        }

        if ($scope = $_values['scope'] ?? null) {
            $scope = strtok($scope, $separator = $this->OAuth2Provider->getScopeSeparator());
            while ($scope !== false) {
                $scopes[] = $scope;
                $scope = strtok($separator);
            }
        }

        $token = new AccessToken(
            $accessToken,
            $tokenType,
            $expires,
            $scopes ?? $this->OAuth2Provider->getDefaultScopes(),
            $claims
        );

        Cache::set($this->OAuth2TokenKey, $token, $token->Expires->getTimestamp());

        if ($id = $_values['id_token'] ?? null) {
            $id = $this->getValidJsonWebToken($id, true);
            // Keep the ID token until access and/or refresh tokens expire
            Cache::set("{$this->OAuth2TokenKey}:id", $id);
        }

        if ($refresh = $_token->getRefreshToken()) {
            Cache::set("{$this->OAuth2TokenKey}:refresh", $refresh);
        }

        $this->receiveOAuth2Token($_token);

        return $token;
    }

    /**
     * Remove any tokens issued by the OAuth 2.0 provider from the cache
     *
     * Call this method to authorize with the provider from scratch when an
     * access token is next requested.
     *
     * @return $this
     */
    final protected function flushAccessToken()
    {
        Console::debug('Flushing cached tokens');
        Cache::delete($this->OAuth2TokenKey);
        Cache::delete("{$this->OAuth2TokenKey}:id");
        Cache::delete("{$this->OAuth2TokenKey}:refresh");
        Cache::delete("{$this->OAuth2TokenKey}:state");

        return $this;
    }

    /**
     * Validates and decodes a token issued by the OAuth 2.0 provider
     *
     * The provider's JSON Web Key Set (JWKS) is required for signature
     * validation, so {@see OAuth2Client::getOAuth2JsonWebKeySetUrl()} must
     * return a URL.
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
    private function getValidJsonWebToken(string $token, bool $required = false): ?array
    {
        $refreshKeys = false;

        // Replace the JWKS (which is cached indefinitely) when signature
        // validation fails
        do {
            if (!($jwks = $this->getJsonWebKeySet($refreshKeys))) {
                return false;
            }
            // If there are any keys with no "alg"orithm (hello, Microsoft
            // Identity Platform), `JWK::parseKeySet()` fails, so extract "alg"
            // from the token and pass it to `JWK::parseKeySet()`
            if (!($alg ?? null) && array_filter(
                $jwks['keys'] ?? [], fn(array $key) => !array_key_exists('alg', $key)
            )) {
                if (count($parts = explode('.', $token)) === 3 &&
                        ($header = base64_decode(strtr($parts[0], '-_', '+/'), true)) &&
                        ($header = json_decode($header, true))) {
                    $alg = $header['alg'] ?? null;
                }
            }
            try {
                return (array) JWT::decode(
                    $token,
                    JWK::parseKeySet($jwks, $alg ?? null)
                );
            } catch (SignatureInvalidException $ex) {
                // If validation failed after refreshing the JWKS, bail out
                if ($refreshKeys) {
                    if ($required) {
                        throw $ex;
                    }
                    return null;
                }
                $refreshKeys = true;
            }
        } while (true);
    }

    private function getJsonWebKeySet(bool $refresh = false): array
    {
        return CurlerBuilder::build()
            ->baseUrl($this->getOAuth2JsonWebKeySetUrl())
            ->cacheResponse()
            ->expiry(0)
            ->if($refresh, fn(CurlerBuilder $curlerB) => $curlerB->flush())
            ->go()
            ->get();
    }

    /**
     * Get the decoded ID token most recently issued with an access token by the
     * OAuth 2.0 provider
     *
     * @return array|null
     */
    final protected function getIdToken(): ?array
    {
        // Don't [re-]authorize for an ID token that won't be issued
        if (Cache::has($this->OAuth2TokenKey, 0) &&
                !Cache::has("{$this->OAuth2TokenKey}:id", 0)) {
            return null;
        }
        // Don't return stale identity information
        if (!$this->hasAccessToken()) {
            $this->getAccessToken();
        }

        return Cache::get("{$this->OAuth2TokenKey}:id") ?: null;
    }
}
