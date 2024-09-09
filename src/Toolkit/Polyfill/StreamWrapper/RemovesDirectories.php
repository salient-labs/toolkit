<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

interface RemovesDirectories extends StreamWrapperInterface
{
    /**
     * Removes a directory
     *
     * This method is called in response to {@see rmdir()}.
     *
     * @param string $path The directory URL which should be removed.
     * @param int $options A bitwise mask of values, such as
     * {@see \STREAM_MKDIR_RECURSIVE}.
     * @return bool `true` on success or `false` on failure.
     */
    public function rmdir(string $path, int $options): bool;
}
