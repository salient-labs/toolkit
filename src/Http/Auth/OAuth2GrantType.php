<?php declare(strict_types=1);

namespace Lkrms\Http\Auth;

use Lkrms\Concept\Enumeration;

/**
 * OAuth 2.0 grant types
 *
 * @extends Enumeration<string>
 */
class OAuth2GrantType extends Enumeration
{
    public const AUTHORIZATION_CODE = 'authorization_code';

    public const REFRESH_TOKEN = 'refresh_token';

    public const CLIENT_CREDENTIALS = 'client_credentials';
}
