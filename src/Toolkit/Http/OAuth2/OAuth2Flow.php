<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

/**
 * OAuth 2.0 flows
 */
interface OAuth2Flow
{
    /**
     * Client Credentials flow
     */
    public const CLIENT_CREDENTIALS = 0;

    /**
     * Authorization Code flow
     */
    public const AUTHORIZATION_CODE = 1;
}
