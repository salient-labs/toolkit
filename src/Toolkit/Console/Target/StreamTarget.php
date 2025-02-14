<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Concept\ConsoleStreamTarget;
use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Core\Facade\Err;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\File;
use Salient\Utility\Str;
use DateTime;
use DateTimeZone;
use LogicException;

/**
 * Writes console output to a PHP stream
 */
final class StreamTarget extends ConsoleStreamTarget
{
    public const DEFAULT_TIMESTAMP_FORMAT = '[d M y H:i:s.vO] ';

    /** @var resource|null */
    private $Stream;
    private bool $IsCloseable;
    private ?string $Uri;
    private bool $AddTimestamp;
    private string $TimestampFormat;
    private ?DateTimeZone $Timezone;
    private bool $IsStdout;
    private bool $IsStderr;
    private bool $IsTty;
    private ?string $Path = null;
    private static bool $HasPendingClearLine = false;

    /**
     * @param resource $stream
     * @param DateTimeZone|string|null $timezone
     */
    private function __construct(
        $stream,
        bool $closeable,
        ?bool $addTimestamp,
        ?string $timestampFormat,
        $timezone
    ) {
        $this->applyStream($stream);

        $this->IsCloseable = $closeable;
        $this->AddTimestamp = $addTimestamp || (
            $addTimestamp === null && !$this->IsStdout && !$this->IsStderr
        );

        if ($this->AddTimestamp) {
            $this->TimestampFormat = Str::coalesce(
                $timestampFormat,
                self::DEFAULT_TIMESTAMP_FORMAT,
            );
            $this->Timezone = is_string($timezone)
                ? new DateTimeZone($timezone)
                : $timezone;
        }
    }

    /**
     * @param resource $stream
     */
    private function applyStream($stream): void
    {
        $meta = stream_get_meta_data($stream);

        $this->Stream = $stream;
        $this->Uri = $meta['uri'] ?? null;
        $this->IsStdout = $this->Uri === 'php://stdout';
        $this->IsStderr = $this->Uri === 'php://stderr';
        $this->IsTty = stream_isatty($stream);

        stream_set_write_buffer($stream, 0);
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
    public function close(): void
    {
        if (!$this->Stream) {
            return;
        }

        if ($this->IsTty && self::$HasPendingClearLine && is_resource($this->Stream)) {
            $this->clearLine(true);
        }

        if ($this->IsCloseable) {
            File::close($this->Stream, $this->Path);
        }

        $this->Stream = null;
        $this->Uri = null;
        $this->IsStdout = false;
        $this->IsStderr = false;
        $this->IsTty = false;
        $this->Path = null;
        $this->setPrefix(null);
    }

    /**
     * @inheritDoc
     */
    public function reopen(?string $path = null): void
    {
        $this->assertIsValid();

        if ($this->Path === null) {
            throw new LogicException(sprintf(
                'Only instances created by %s::fromPath() can be reopened',
                static::class,
            ));
        }

        if ($path === null || $path === '') {
            $path = $this->Path;
        }

        File::close($this->Stream, $this->Path);

        if (!File::same($path, $this->Path)) {
            File::create($path, 0600);
        }

        $stream = File::open($path, 'a');
        $this->applyStream($stream);
        $this->Path = $path;
    }

    /**
     * Creates a new StreamTarget object backed by an open PHP stream
     *
     * @param resource $stream
     * @param bool $closeable If `true`, call {@see File::close()} to close
     * `$stream` when the target is closed.
     * @param bool|null $addTimestamp If `null`, add timestamps if `$stream` is
     * not `STDOUT` or `STDERR`.
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public static function fromStream(
        $stream,
        bool $closeable = false,
        ?bool $addTimestamp = null,
        ?string $timestampFormat = StreamTarget::DEFAULT_TIMESTAMP_FORMAT,
        $timezone = null
    ): self {
        if (!File::isStream($stream)) {
            throw new InvalidArgumentTypeException(1, 'stream', 'resource (stream)', $stream);
        }

        return new self($stream, $closeable, $addTimestamp, $timestampFormat, $timezone);
    }

    /**
     * Open a file in append mode and return a console output target for it
     *
     * @param bool|null $addTimestamp If `null`, add timestamps if `$path` does
     * not resolve to `STDOUT` or `STDERR`.
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public static function fromPath(
        string $path,
        ?bool $addTimestamp = null,
        ?string $timestampFormat = StreamTarget::DEFAULT_TIMESTAMP_FORMAT,
        $timezone = null
    ): self {
        File::create($path, 0600);
        $stream = File::open($path, 'a');
        $instance = new self($stream, true, $addTimestamp, $timestampFormat, $timezone);
        $instance->Path = $path;
        return $instance;
    }

    /**
     * @inheritDoc
     */
    protected function writeToTarget($level, string $message, array $context): void
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
                $this->clearLine($level < Level::WARNING);
            }
            if ($message === "\r") {
                return;
            }
            if ($message !== '' && $message[-1] === "\r") {
                File::write($this->Stream, self::WRAP_OFF . rtrim($message, "\r"));
                self::$HasPendingClearLine = true;
                return;
            }
        }

        File::write($this->Stream, rtrim($message, "\r") . "\n");
    }

    public function getPath(): ?string
    {
        return $this->Path;
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

    /**
     * @phpstan-assert !null $this->Stream
     */
    private function clearLine(bool $preserveOutputOnError = false): void
    {
        $this->assertIsValid();

        $data = $preserveOutputOnError
            && Err::isLoaded()
            && Err::isShuttingDownOnError()
                ? self::WRAP_ON . "\n"
                : "\r" . self::CLEAR_LINE . self::WRAP_ON;
        File::write($this->Stream, $data);
        self::$HasPendingClearLine = false;
    }
}
