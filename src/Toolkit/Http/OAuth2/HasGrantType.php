<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

/**
 * @api
 */
interface HasGrantType
{
    /**
     * Authorization code
     *
     * - \[RFC6749] Section 4.1 ("Authorization Code Grant")
     * - \[OpenID.Core] Section 3.1 ("Authentication using the Authorization
     *   Code Flow")
     * - \[OpenID.Core] Section 3.3 ("Authentication using the Hybrid Flow")
     */
    public const GRANT_AUTHORIZATION_CODE = 'authorization_code';

    /**
     * Resource owner password
     *
     * - \[RFC6749] Section 4.3 ("Resource Owner Password Credentials Grant")
     */
    public const GRANT_PASSWORD = 'password';

    /**
     * Client credentials
     *
     * - \[RFC6749] Section 4.4 ("Client Credentials Grant")
     */
    public const GRANT_CLIENT_CREDENTIALS = 'client_credentials';

    /**
     * Device code
     *
     * - \[RFC8628] ("OAuth 2.0 Device Authorization Grant")
     */
    public const GRANT_DEVICE_CODE = 'urn:ietf:params:oauth:grant-type:device_code';

    /**
     * Refresh token
     *
     * - \[RFC6749] Section 6 ("Refreshing an Access Token")
     */
    public const GRANT_REFRESH_TOKEN = 'refresh_token';
}
