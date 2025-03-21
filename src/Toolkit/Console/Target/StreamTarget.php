<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Core\Facade\Err;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\File;
use DateTime;
use DateTimeZone;
use LogicException;

/**
 * Writes console output to a file or stream
 *
 * @api
 */
class StreamTarget extends AbstractStreamTarget
{
    public const DEFAULT_TIMESTAMP_FORMAT = '[d M y H:i:s.vO] ';

    /** @var resource|null */
    protected $Stream;
    protected ?string $Uri;
    protected bool $IsStdout;
    protected bool $IsStderr;
    protected bool $IsTty;
    protected bool $Close;
    protected bool $AddTimestamp;
    protected string $TimestampFormat;
    protected ?DateTimeZone $Timezone;
    protected ?string $Filename = null;

    private static bool $HasPendingClearLine = false;

    /**
     * @api
     *
     * @param resource $stream
     * @param bool $close If `true`, the stream is closed when the target is
     * closed.
     * @param bool|null $addTimestamp If `null` (the default), timestamps are
     * added if `$stream` is not `STDOUT` or `STDERR`.
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public function __construct(
        $stream,
        bool $close = false,
        ?bool $addTimestamp = null,
        string $timestampFormat = StreamTarget::DEFAULT_TIMESTAMP_FORMAT,
        $timezone = null
    ) {
        if (!File::isStream($stream)) {
            throw new InvalidArgumentTypeException(1, 'stream', 'resource (stream)', $stream);
        }

        $this->applyStream($stream);
        $this->Close = $close;
        $this->AddTimestamp = $addTimestamp
            || ($addTimestamp === null && !$this->IsStdout && !$this->IsStderr);
        if ($this->AddTimestamp) {
            $this->TimestampFormat = $timestampFormat;
            $this->Timezone = is_string($timezone)
                ? new DateTimeZone($timezone)
                : $timezone;
        }
    }

    /**
     * Opens a file in append mode and creates a new StreamTarget object for it
     *
     * @param string $filename Created with file mode `0600` if it doesn't
     * exist.
     * @param bool|null $addTimestamp If `null` (the default), timestamps are
     * added if `$filename` does not resolve to `STDOUT` or `STDERR`.
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public static function fromFile(
        string $filename,
        ?bool $addTimestamp = null,
        string $timestampFormat = StreamTarget::DEFAULT_TIMESTAMP_FORMAT,
        $timezone = null
    ): self {
        File::create($filename, 0600);
        $stream = File::open($filename, 'a');
        $instance = new self($stream, true, $addTimestamp, $timestampFormat, $timezone);
        $instance->Filename = $filename;
        return $instance;
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (!$this->Stream) {
            return;
        }

        if (
            $this->IsTty
            && self::$HasPendingClearLine
            && File::isStream($this->Stream)
        ) {
            $this->clearLine(true);
        }

        if ($this->Close) {
            File::close($this->Stream, $this->Filename ?? $this->Uri);
        }

        $this->Stream = null;
        $this->Uri = null;
        $this->IsStdout = false;
        $this->IsStderr = false;
        $this->IsTty = false;
        $this->Filename = null;
    }

    /**
     * @inheritDoc
     */
    public function isStdout(): bool
    {
        return $this->IsStdout;
    }

    /**
     * @inheritDoc
     */
    public function isStderr(): bool
    {
        return $this->IsStderr;
    }

    /**
     * @inheritDoc
     */
    public function isTty(): bool
    {
        return $this->IsTty;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): ?string
    {
        return $this->Filename ?? $this->Uri;
    }

    /**
     * @inheritDoc
     */
    public function reopen(?string $filename = null): void
    {
        $this->assertIsValid();

        // Do nothing if there is nothing to reopen, or if the stream can't be
        // closed
        $filename ??= $this->Filename;
        if ($filename === null || !$this->Close) {
            return;
        }

        File::close($this->Stream, $this->Filename ?? $this->Uri);
        File::create($filename, 0600);
        $this->applyStream(File::open($filename, 'a'));
        $this->Filename = $filename;
    }

    /**
     * @inheritDoc
     */
    protected function doWrite(int $level, string $message, array $context): void
    {
        $this->assertIsValid();

        if ($this->AddTimestamp) {
            $now = (new DateTime('now', $this->Timezone))->format($this->TimestampFormat);
            $message = $now . str_replace("\n", "\n" . $now, $message);
        }

        // If writing a progress message to a TTY, suppress the usual newline
        // and write a "clear to end of line" sequence before the next message
        if ($this->IsTty) {
            if (self::$HasPendingClearLine) {
                $this->clearLine($level < Console::LEVEL_WARNING);
            }
            if ($message === "\r") {
                return;
            }
            if ($message !== '' && $message[-1] === "\r") {
                File::write($this->Stream, self::NO_AUTO_WRAP . rtrim($message, "\r"));
                self::$HasPendingClearLine = true;
                return;
            }
        }

        File::write($this->Stream, rtrim($message, "\r") . "\n");
    }

    /**
     * @param resource $stream
     */
    protected function applyStream($stream): void
    {
        $this->Stream = $stream;
        $this->Uri = stream_get_meta_data($stream)['uri'] ?? null;
        $this->IsStdout = $this->Uri === 'php://stdout';
        $this->IsStderr = $this->Uri === 'php://stderr';
        $this->IsTty = stream_isatty($stream);

        stream_set_write_buffer($stream, 0);
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    private function clearLine(bool $checkForError = false): void
    {
        $this->assertIsValid();

        $data = $checkForError
            && Err::isLoaded()
            && Err::isShuttingDownOnError()
                ? self::AUTO_WRAP . "\n"
                : "\r" . self::CLEAR_LINE . self::AUTO_WRAP;
        File::write($this->Stream, $data);
        self::$HasPendingClearLine = false;
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    protected function assertIsValid(): void
    {
        if (!$this->Stream) {
            throw new LogicException('Target is closed');
        }
    }
}
