<?php

declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Facade\Convert;

/**
 * Thrown when an unimplemented sync provider method is called
 *
 */
class SyncProviderMethodNotImplementedException extends \Lkrms\Exception\Exception
{
    public function __construct(string $provider, string $entity, string $method)
    {
        parent::__construct(sprintf(
            "%s has not implemented %s for %s",
            Convert::classToBasename($provider),
            Convert::methodToFunction($method),
            $entity
        ));
    }
}
