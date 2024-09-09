<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

interface CreatesDirectories extends StreamWrapperInterface
{
    /**
     * Create a directory
     *
     * This method is called in response to {@see mkdir()}.
     *
     * @param string $path Directory which should be created.
     * @param int $mode The value passed to {@see mkdir()}.
     * @param int $options A bitwise mask of values, such as
     * {@see \STREAM_MKDIR_RECURSIVE}.
     * @return bool `true` on success or `false` on failure.
     */
    public function mkdir(string $path, int $mode, int $options): bool;
}
