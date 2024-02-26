<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

use Salient\Core\AbstractEnumeration;

/**
 * OAuth 2.0 grant types
 *
 * @extends AbstractEnumeration<string>
 */
class OAuth2GrantType extends AbstractEnumeration
{
    public const AUTHORIZATION_CODE = 'authorization_code';

    public const REFRESH_TOKEN = 'refresh_token';

    public const CLIENT_CREDENTIALS = 'client_credentials';
}
