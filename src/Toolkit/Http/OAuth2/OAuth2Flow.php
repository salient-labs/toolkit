<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

use Salient\Core\AbstractEnumeration;

/**
 * OAuth 2.0 flows
 *
 * @extends AbstractEnumeration<int>
 */
class OAuth2Flow extends AbstractEnumeration
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
