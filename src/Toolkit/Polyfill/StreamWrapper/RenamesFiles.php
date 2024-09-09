<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

interface RenamesFiles extends StreamWrapperInterface
{
    /**
     * Renames a file or directory
     *
     * This method is called in response to {@see rename()}.
     *
     * @param string $path_from The URL to the current file.
     * @param string $path_to The URL which the `path_from` should be renamed
     * to.
     * @return bool `true` on success or `false` on failure.
     */
    public function rename(string $path_from, string $path_to): bool;
}
