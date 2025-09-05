<?php declare(strict_types=1);

namespace Salient\Http;

use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\CredentialInterface;

/**
 * @api
 */
class GenericCredential implements CredentialInterface, Immutable
{
    private string $AuthenticationScheme;
    private string $Credential;

    /**
     * @api
     */
    public function __construct(
        string $credential,
        string $authenticationScheme
    ) {
        $this->AuthenticationScheme = $authenticationScheme;
        $this->Credential = $credential;
    }

    /**
     * @inheritDoc
     */
    public function getAuthenticationScheme(): string
    {
        return $this->AuthenticationScheme;
    }

    /**
     * @inheritDoc
     */
    public function getCredential(): string
    {
        return $this->Credential;
    }
}
