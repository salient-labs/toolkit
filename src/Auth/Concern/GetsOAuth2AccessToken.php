<?php declare(strict_types=1);

namespace Lkrms\Auth\Concern;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lkrms\Auth\AccessToken;
use Lkrms\Concern\TReadable;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Support\Dictionary\HttpRequestMethod;
use Lkrms\Support\Http\HttpRequest;
use Lkrms\Support\Http\HttpResponse;
use Lkrms\Support\Http\HttpServer;
use RuntimeException;
use Throwable;

/**
 * An OAuth 2.0 client implementation
 *
 * @property-read HttpServer $OAuth2Listener
 * @property-read string $OAuth2RedirectUri
 * @property-read AbstractProvider $OAuth2Provider
 * @property-read string $OAuth2TokenKey
 *
 * @psalm-require-implements \Lkrms\Contract\IReadable
 */
trait GetsOAuth2AccessToken
{
    use TReadable;

    abstract protected function getOAuth2Listener(): HttpServer;

    abstract protected function getOAuth2Provider(): AbstractProvider;

    abstract protected function getOAuth2JsonWebKeySetUrl(): string;

    abstract protected function receiveOAuth2Token(AccessTokenInterface $token): void;

    public static function getReadable(): array
    {
        return [];
    }

    /**
     * @var HttpServer|null
     */
    private $_OAuth2Listener;

    /**
     * @var string|null
     */
    private $_OAuth2RedirectUri;

    /**
     * @var AbstractProvider|null
     */
    private $_OAuth2Provider;

    /**
     * @var string|null
     */
    private $_OAuth2TokenKey;

    /**
     * @internal
     */
    final protected function _getOAuth2Listener(): HttpServer
    {
        return $this->_OAuth2Listener
                   ?: ($this->_OAuth2Listener = $this->getOAuth2Listener());
    }

    /**
     * @internal
     */
    final protected function _getOAuth2RedirectUri(): string
    {
        return $this->_OAuth2RedirectUri
                   ?: $this->_OAuth2RedirectUri =
                       sprintf('%s/oauth2/callback',
                               $this->OAuth2Listener->getBaseUrl());
    }

    /**
     * @internal
     */
    final protected function _getOAuth2Provider(): AbstractProvider
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
     * possible, authenticate with the provider interactively
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
            !array_diff($scopes, $token->Claims['scope'] ?? []);
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
        Console::debug('Requesting access token with refresh_token');
        $token = $this->requestAccessToken('refresh_token', ['refresh_token' => $refreshToken]);

        return true;
    }

    /**
     * Get an access token from the OAuth 2.0 provider using the Authorization
     * Code Flow
     *
     */
    final protected function authorize(): AccessToken
    {
        $this->flushAccessToken();

        $url   = $this->OAuth2Provider->getAuthorizationUrl();
        $state = $this->OAuth2Provider->getState();
        Cache::set("{$this->OAuth2TokenKey}:state", $state);

        Console::debug(
            'Starting HTTP server to receive authorization_code:',
            sprintf('%s:%d',
                    $this->OAuth2Listener->Host,
                    $this->OAuth2Listener->Port)
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

        Console::debug('Requesting access token with authorization_code');

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
        $_token = $this->OAuth2Provider->getAccessToken($grant, $parameters);

        $tokenString = $_token->getToken();
        $claims      = $this->getValidJsonWebToken($tokenString);

        $token = new AccessToken(
            $tokenString,
            $_token->getExpires() ?: $claims['exp'] ?? 0,
            $claims
        );

        Cache::set($this->OAuth2TokenKey, $token, $token->Expires->getTimestamp());

        if ($id = $_token->getValues()['id_token'] ?? null) {
            $id = $this->getValidJsonWebToken($id);
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
     * Call this method to force interactive authentication when an access token
     * is next requested.
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
     * The provider's JSON Web Key Set (JWKS) is used to perform signature
     * validation.
     *
     * @return array<string,mixed>
     */
    private function getValidJsonWebToken(string $token): array
    {
        $refreshKeys = false;

        // Replace the JWKS (which is cached indefinitely) when signature
        // validation fails
        do {
            try {
                return (array) JWT::decode(
                    $token,
                    JWK::parseKeySet($this->getJsonWebKeySet($refreshKeys))
                );
            } catch (SignatureInvalidException $ex) {
                // If validation failed after refreshing the JWKS, bail out
                if ($refreshKeys) {
                    throw $ex;
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
        // Don't return stale identity information
        if (!$this->hasAccessToken()) {
            $this->getAccessToken();
        }

        return Cache::get("{$this->OAuth2TokenKey}:id") ?: null;
    }
}
