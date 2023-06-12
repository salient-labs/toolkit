<?php declare(strict_types=1);

namespace Lkrms\Auth\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Supported OAuth 2.0 flows
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
