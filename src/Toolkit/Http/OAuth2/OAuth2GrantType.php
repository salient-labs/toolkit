<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

/**
 * OAuth 2.0 grant types
 */
interface OAuth2GrantType
{
    public const AUTHORIZATION_CODE = 'authorization_code';
    public const REFRESH_TOKEN = 'refresh_token';
    public const CLIENT_CREDENTIALS = 'client_credentials';
}
