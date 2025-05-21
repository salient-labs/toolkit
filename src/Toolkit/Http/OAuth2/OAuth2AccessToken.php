<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

use Salient\Http\GenericToken;

/**
 * @api
 */
class OAuth2AccessToken extends GenericToken
{
    /** @var string[] */
    private array $Scopes;
    /** @var array<string,mixed> */
    private array $Claims;

    /**
     * @api
     *
     * @param string[] $scopes
     * @param array<string,mixed> $claims
     */
    public function __construct(
        string $token,
        $expires = null,
        array $scopes = [],
        array $claims = []
    ) {
        $this->Scopes = $scopes;
        $this->Claims = $claims;

        parent::__construct($token, 'Bearer', $expires);
    }

    /**
     * Get the token's scopes
     *
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->Scopes;
    }

    /**
     * Get the token's claims
     *
     * @return array<string,mixed>
     */
    public function getClaims(): array
    {
        return $this->Claims;
    }
}
