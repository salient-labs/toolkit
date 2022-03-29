<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Curler\CachingCurler;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Sync\SyncProvider;

/**
 * Base class for HTTP-based RESTful API providers
 *
 * @package Lkrms
 */
abstract class HttpSyncProvider extends SyncProvider
{
    abstract protected function getBaseUrl(): string;

    abstract protected function getHeaders(): ?CurlerHeaders;

    protected function headersCallback(array $headers): array
    {
        return $this->getBackendIdentifier();
    }

    final public function getCurler(string $path, int $expiry = null): Curler
    {
        if (func_num_args() > 1)
        {
            return new CachingCurler(
                $this->getBaseUrl() . $path,
                $this->getHeaders(),
                $expiry,
                [$this, "headersCallback"]
            );
        }
        else
        {
            return new Curler(
                $this->getBaseUrl() . $path,
                $this->getHeaders()
            );
        }
    }
}

