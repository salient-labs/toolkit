<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

/**
 * @phpstan-require-extends StreamWrapper
 */
interface StreamWrapperInterface
{
    /**
     * Constructs a new stream wrapper
     *
     * Called when opening the stream wrapper, right before
     * {@see StreamWrapperInterface::stream_open()}.
     */
    public function __construct();

    /**
     * Retrieve the underlying resource
     *
     * This method is called in response to {@see stream_select()}.
     *
     * @param int $cast_as Can be {@see \STREAM_CAST_FOR_SELECT} when
     * {@see stream_select()} is calling **stream_cast()** or
     * {@see \STREAM_CAST_AS_STREAM} when **stream_cast()** is called for other
     * uses.
     * @return resource|false The underlying stream resource used by the
     * wrapper, or `false`.
     */
    public function stream_cast(int $cast_as);

    /**
     * Close a resource
     *
     * This method is called in response to {@see fclose()}.
     *
     * All resources that were locked, or allocated, by the wrapper should be
     * released.
     */
    public function stream_close(): void;

    /**
     * Tests for end-of-file on a file pointer
     *
     * This method is called in response to {@see feof()}.
     *
     * @return bool `true` if the read/write position is at the end of the
     * stream and if no more data is available to be read, or `false` otherwise.
     */
    public function stream_eof(): bool;

    /**
     * Flushes the output
     *
     * This method is called in response to {@see fflush()} and when the stream
     * is being closed while any unflushed data has been written to it before.
     *
     * If you have cached data in your stream but not yet stored it into the
     * underlying storage, you should do so now.
     *
     * @return bool `true` if the cached data was successfully stored (or if
     * there was no data to store), or `false` if the data could not be stored.
     */
    public function stream_flush(): bool;

    /**
     * Change stream metadata
     *
     * This method is called to set metadata on the stream. It is called when
     * one of the following functions is called on a stream URL:
     *
     * - {@see touch()}
     * - {@see chmod()}
     * - {@see chown()}
     * - {@see chgrp()}
     *
     * @param string $path The file path or URL to set metadata. Note that in
     * the case of a URL, it must be a :// delimited URL. Other URL forms are
     * not supported.
     * @param int $option One of:
     *
     * - {@see \STREAM_META_TOUCH} (The method was called in response to
     *   {@see touch()})
     * - {@see \STREAM_META_OWNER_NAME} (The method was called in response to
     *   {@see chown()})
     * - {@see \STREAM_META_OWNER} (The method was called in response to
     *   {@see chown()})
     * - {@see \STREAM_META_GROUP_NAME} (The method was called in response to
     *   {@see chgrp()})
     * - {@see \STREAM_META_GROUP} (The method was called in response to
     *   {@see chgrp()})
     * - {@see \STREAM_META_ACCESS} (The method was called in response to
     *   {@see chmod()})
     * @param mixed $value If `option` is
     *
     * - {@see \STREAM_META_TOUCH}: Array consisting of two arguments of the
     *   {@see touch()} function.
     * - {@see \STREAM_META_OWNER_NAME} or {@see \STREAM_META_GROUP_NAME}: The
     *   name of the owner user/group as `string`.
     * - {@see \STREAM_META_OWNER} or {@see \STREAM_META_GROUP}: The value of
     *   the owner user/group as `int`.
     * - {@see \STREAM_META_ACCESS}: The argument of the {@see chmod()} function
     *   as `int`.
     * @return bool `true` on success or `false` on failure. If `option` is not
     * implemented, `false` should be returned.
     */
    public function stream_metadata(string $path, int $option, $value): bool;

    /**
     * Opens file or URL
     *
     * This method is called immediately after the wrapper is initialized (e.g.
     * by {@see fopen()} and {@see file_get_contents()}).
     *
     * @param string $path Specifies the URL that was passed to the original
     * function. Only URLs delimited by :// are supported.
     * @param string $mode The mode used to open the file, as detailed for
     * {@see fopen()}.
     * @param int $options Holds additional flags set by the streams API. It can
     * hold one or more of the following values OR'd together.
     *
     * - {@see \STREAM_USE_PATH}: If `path` is relative, search for the resource
     *   using the include_path.
     * - {@see \STREAM_REPORT_ERRORS}: If this flag is set, you are responsible
     *   for raising errors using {@see trigger_error()} during opening of the
     *   stream. If this flag is not set, you should not raise any errors.
     * @param string|null $opened_path If the `path` is opened successfully, and
     * {@see \STREAM_USE_PATH} is set in `options`, `opened_path` should be set
     * to the full path of the file/resource that was actually opened.
     * @return bool `true` on success or `false` on failure.
     */
    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$opened_path
    ): bool;

    /**
     * Read from stream
     *
     * This method is called in response to {@see fread()} and {@see fgets()}.
     *
     * > Remember to update the read/write position of the stream (by the number
     * > of bytes that were successfully read).
     *
     * @param int $count How many bytes of data from the current position should
     * be returned.
     * @return string|false If there are less than `count` bytes available, as
     * many as are available should be returned. If no more data is available,
     * an empty string should be returned. To signal that reading failed,
     * `false` should be returned.
     */
    public function stream_read(int $count);

    /**
     * Seeks to specific location in a stream
     *
     * This method is called in response to {@see fseek()}.
     *
     * @param int $offset The stream offset to seek to.
     * @param int $whence Possible values:
     *
     * - {@see \SEEK_SET}: Set position equal to `offset` bytes.
     * - {@see \SEEK_CUR}: Set position to current location plus `offset`.
     *   (Never used by current implementation; always internally converted to
     *   {@see \SEEK_SET}.)
     * - {@see \SEEK_END}: Set position to end-of-file plus `offset`.
     * @return bool `true` if the position was updated, `false` otherwise.
     */
    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool;

    /**
     * Change stream options
     *
     * @param int $option One of:
     *
     * - {@see \STREAM_OPTION_BLOCKING} (The method was called in response to
     *   {@see stream_set_blocking()})
     * - {@see \STREAM_OPTION_READ_TIMEOUT} (The method was called in response
     *   to {@see stream_set_timeout()})
     * - {@see \STREAM_OPTION_READ_BUFFER} (The method was called in response to
     *   {@see stream_set_read_buffer()})
     * - {@see \STREAM_OPTION_WRITE_BUFFER} (The method was called in response
     *   to {@see stream_set_write_buffer()})
     * @param int $arg1 If `option` is
     *
     * - {@see \STREAM_OPTION_BLOCKING}: requested blocking mode (1 meaning
     *   block, 0 not blocking).
     * - {@see \STREAM_OPTION_READ_TIMEOUT}: the timeout in seconds.
     * - {@see \STREAM_OPTION_READ_BUFFER}: buffer mode
     *   ({@see \STREAM_BUFFER_NONE} or {@see \STREAM_BUFFER_FULL}).
     * - {@see \STREAM_OPTION_WRITE_BUFFER}: buffer mode
     *   ({@see \STREAM_BUFFER_NONE} or {@see \STREAM_BUFFER_FULL}).
     * @param int $arg2 If `option` is
     *
     * - {@see \STREAM_OPTION_BLOCKING}: not set.
     * - {@see \STREAM_OPTION_READ_TIMEOUT}: the timeout in microseconds.
     * - {@see \STREAM_OPTION_READ_BUFFER}: the requested buffer size.
     * - {@see \STREAM_OPTION_WRITE_BUFFER}: the requested buffer size.
     * @return bool `true` on success or `false` on failure. If `option` is not
     * implemented, `false` should be returned.
     */
    public function stream_set_option(int $option, int $arg1, int $arg2): bool;

    /**
     * Retrieve information about a file resource
     *
     * This method is called in response to {@see fstat()}.
     *
     * @return mixed[]|false
     */
    public function stream_stat();

    /**
     * Retrieve the current position of a stream
     *
     * This method is called in response to {@see fseek()}.
     */
    public function stream_tell(): int;

    /**
     * Truncate stream
     *
     * This method is called in response to {@see ftruncate()}.
     *
     * @return bool `true` on success or `false` on failure.
     */
    public function stream_truncate(int $new_size): bool;

    /**
     * Write to stream
     *
     * This method is called in response to {@see fwrite()}.
     *
     * > Remember to update the current position of the stream by number of
     * > bytes that were successfully written.
     *
     * @param string $data Should be stored into the underlying stream. If there
     * is not enough room in the underlying stream, store as much as possible.
     * @return int The number of bytes that were successfully stored, or `0` if
     * none could be stored.
     */
    public function stream_write(string $data): int;

    /**
     * Retrieve information about a file
     *
     * This method is called in response to all {@see stat()} related functions.
     *
     * @param string $path The file path or URL to stat. Note that in the case
     * of a URL, it must be a :// delimited URL. Other URL forms are not
     * supported.
     * @param int $flags Holds additional flags set by the streams API. It can
     * hold one or more of the following values OR'd together.
     *
     * - {@see \STREAM_URL_STAT_LINK}: For resources with the ability to link to
     *   other resource (such as an HTTP Location: forward, or a filesystem
     *   symlink). This flag specified that only information about the link
     *   itself should be returned, not the resource pointed to by the link.
     *   This flag is set in response to calls to {@see lstat()},
     *   {@see is_link()}, or {@see filetype()}.
     * - {@see \STREAM_URL_STAT_QUIET}: If this flag is set, your wrapper should
     *   not raise any errors. If this flag is not set, you are responsible for
     *   reporting errors using the {@see trigger_error()} function.
     * @return mixed[]|false `false` on failure, otherwise an `array` with the
     * same elements returned by {@see stat()}. Unknown or unavailable values
     * should be set to a rational value (usually `0`). Special attention should
     * be paid to `mode` as documented under {@see stat()}.
     */
    public function url_stat(string $path, int $flags);
}
