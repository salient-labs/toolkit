<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface CredentialInterface
{
    /**
     * Get the authentication scheme of the credential, e.g. "Bearer"
     */
    public function getAuthenticationScheme(): string;

    /**
     * Get the credential
     */
    public function getCredential(): string;
}
