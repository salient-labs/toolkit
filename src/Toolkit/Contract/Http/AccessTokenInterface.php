<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface AccessTokenInterface
{
    /**
     * Get the access token
     */
    public function getToken(): string;

    /**
     * Get the access token's type, e.g. "Bearer"
     */
    public function getTokenType(): string;
}
