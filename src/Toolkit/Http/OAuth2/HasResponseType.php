<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

/**
 * @api
 */
interface HasResponseType
{
    /**
     * Authorization code
     *
     * - \[RFC6749] Section 4.1 ("Authorization Code Grant")
     * - \[OpenID.Core] Section 3.1 ("Authentication using the Authorization
     *   Code Flow")
     */
    public const RESPONSE_CODE = 'code';

    /**
     * Token
     *
     * - \[RFC6749] Section 4.2 ("Implicit Grant")
     */
    public const RESPONSE_TOKEN = 'token';

    /**
     * ID token
     *
     * - \[OpenID.Core] Section 3.2 ("Authentication using the Implicit Flow")
     */
    public const RESPONSE_ID_TOKEN = 'id_token';

    /**
     * ID token + token
     *
     * - \[OpenID.Core] Section 3.2 ("Authentication using the Implicit Flow")
     */
    public const RESPONSE_ID_TOKEN_TOKEN = 'id_token token';

    /**
     * Authorization code + ID token
     *
     * - \[OpenID.Core] Section 3.3 ("Authentication using the Hybrid Flow")
     */
    public const RESPONSE_CODE_ID_TOKEN = 'code id_token';

    /**
     * Authorization code + token
     *
     * - \[OpenID.Core] Section 3.3 ("Authentication using the Hybrid Flow")
     */
    public const RESPONSE_CODE_TOKEN = 'code token';

    /**
     * Authorization code + ID token + token
     *
     * - \[OpenID.Core] Section 3.3 ("Authentication using the Hybrid Flow")
     */
    public const RESPONSE_CODE_ID_TOKEN_TOKEN = 'code id_token token';
}
