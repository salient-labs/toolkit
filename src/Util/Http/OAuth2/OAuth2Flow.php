<?php declare(strict_types=1);

namespace Lkrms\Http\OAuth2;

use Lkrms\Concept\Enumeration;

/**
 * OAuth 2.0 flows
 *
 * @extends Enumeration<int>
 */
class OAuth2Flow extends Enumeration
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
