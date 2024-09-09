<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

interface RemovesFiles extends StreamWrapperInterface
{
    /**
     * Delete a file
     *
     * This method is called in response to {@see unlink()}.
     *
     * @param string $path The file URL which should be deleted.
     * @return bool `true` on success or `false` on failure.
     */
    public function unlink(string $path): bool;
}
