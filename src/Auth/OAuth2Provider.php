<?php declare(strict_types=1);

namespace Lkrms\Auth;

use League\OAuth2\Client\Provider\GenericProvider;

/**
 * A proxy for League\OAuth2\Client\Provider\GenericProvider
 *
 * If {@see \League\OAuth2\Client\Provider\AbstractProvider} is used as the
 * return type of {@see OAuth2Client::getOAuth2Provider()}, the provider's
 * default scopes (and other particulars) are inaccessible.
 *
 * {@see \League\OAuth2\Client\Provider\GenericProvider} surfaces more of the
 * provider's configuration, but returning a `GenericProvider` from
 * `getOAuth2Provider()` would make it impossible to integrate with third-party
 * providers, which inherit `AbstractProvider`.
 *
 * This class is a placeholder that will be replaced with a more flexible
 * implementation.
 *
 */
class OAuth2Provider extends GenericProvider
{
    public function getScopeSeparator()
    {
        return parent::getScopeSeparator();
    }
}
