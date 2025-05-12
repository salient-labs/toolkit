<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface CredentialInterface
{
    /**
     * Get the authentication scheme of the credential, e.g. "Basic", "Digest"
     * or "Bearer"
     */
    public function getAuthenticationScheme(): string;

    /**
     * Get the credential, e.g. a Base64-encoded user ID/password pair, a
     * comma-delimited list of authorization parameters or an OAuth 2.0 access
     * token
     */
    public function getCredential(): string;
}
