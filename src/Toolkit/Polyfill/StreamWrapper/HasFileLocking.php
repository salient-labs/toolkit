<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

interface HasFileLocking extends StreamWrapperInterface
{
    /**
     * Advisory file locking
     *
     * This method is called in response to {@see flock()},
     * {@see file_put_contents()} (when `flags` contains {@see \LOCK_EX}),
     * {@see stream_set_blocking()} and when closing the stream
     * ({@see \LOCK_UN}).
     *
     * @param int $operation One of the following:
     *
     * - {@see \LOCK_SH} to acquire a shared lock (reader).
     * - {@see \LOCK_EX} to acquire an exclusive lock (writer).
     * - {@see \LOCK_UN} to release a lock (shared or exclusive).
     * - {@see \LOCK_NB} if you don't want {@see flock()} to block while
     *   locking (not supported on Windows).
     * @return bool `true` on success or `false` on failure.
     */
    public function stream_lock(int $operation): bool;
}
