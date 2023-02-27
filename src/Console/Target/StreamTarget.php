<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use DateTime;
use Lkrms\Console\Concept\ConsoleTarget;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Support\Dictionary\TtyControlSequence;
use RuntimeException;

/**
 * Write console messages to a stream (e.g. a file or TTY)
 *
 */
final class StreamTarget extends ConsoleTarget
{
    /**
     * @var resource
     */
    private $Stream;

    /**
     * @var bool
     */
    private $AddTimestamp;

    /**
     * @var string
     */
    private $Timestamp = '[d M y H:i:s.vO] ';

    /**
     * @var \DateTimeZone|null
     */
    private $Timezone;

    /**
     * @var bool
     */
    private $IsStdout;

    /**
     * @var bool
     */
    private $IsStderr;

    /**
     * @var bool
     */
    private $IsTty;

    /**
     * @var string
     */
    private $Path;

    /**
     * @var bool
     */
    private $HasPendingClearLine = false;

    /**
     * Use an open stream as a console output target
     *
     * @param resource $stream
     * @param bool|null $addTimestamp If `null`, timestamps will be added unless
     * `$stream` is STDOUT, STDERR, or a TTY
     * @param string|null $timestamp Default: `[d M y H:i:s.vO] `
     * @param \DateTimeZone|string|null $timezone Default: as per
     * `date_default_timezone_set` or INI setting `date.timezone`
     */
    public function __construct($stream, bool $addTimestamp = null, string $timestamp = null, $timezone = null)
    {
        stream_set_write_buffer($stream, 0);

        $this->Stream       = $stream;
        $this->IsStdout     = File::getStreamUri($stream) == 'php://stdout';
        $this->IsStderr     = File::getStreamUri($stream) == 'php://stderr';
        $this->IsTty        = stream_isatty($stream);
        $this->AddTimestamp = !is_null($addTimestamp) ? $addTimestamp : !($this->IsStdout || $this->IsStderr);

        if (!is_null($timestamp)) {
            $this->Timestamp = $timestamp;
        }

        if (!is_null($timezone)) {
            $this->Timezone = Convert::toTimezone($timezone);
        }
    }

    public function isStdout(): bool
    {
        return $this->IsStdout;
    }

    public function isStderr(): bool
    {
        return $this->IsStderr;
    }

    public function isTty(): bool
    {
        return $this->IsTty;
    }

    public function reopen(string $path = null)
    {
        if (!$this->Path) {
            throw new RuntimeException('StreamTarget not created by StreamTarget::fromPath()');
        }

        $path = $path ?: $this->Path;

        if (!fclose($this->Stream) || !File::maybeCreate($path, 0600) || ($stream = fopen($path, 'a')) === false) {
            throw new RuntimeException("Could not close {$this->Path} and open $path");
        }

        $this->Stream = $stream;
        $this->Path   = $path;
    }

    /**
     * Open a file in append mode and return a console output target for it
     *
     * @param bool|null $addTimestamp If `null`, timestamps will be added unless
     * `$path` is STDOUT, STDERR, or a TTY
     * @param string|null $timestamp Default: `[d M y H:i:s.vO] `
     * @param \DateTimeZone|string|null $timezone Default: as per
     * `date_default_timezone_set` or INI setting `date.timezone`
     */
    public static function fromPath(string $path, ?bool $addTimestamp = null, ?string $timestamp = null, $timezone = null): StreamTarget
    {
        if (!File::maybeCreate($path, 0600) || ($stream = fopen($path, 'a')) === false) {
            throw new RuntimeException("Could not open $path");
        }

        $stream       = new StreamTarget($stream, $addTimestamp, $timestamp, $timezone);
        $stream->Path = $path;

        return $stream;
    }

    protected function writeToTarget(int $level, string $message, array $context): void
    {
        if ($this->AddTimestamp) {
            $now     = (new DateTime('now', $this->Timezone))->format($this->Timestamp);
            $message = $now . str_replace("\n", "\n" . $now, $message);
        }

        // If writing a progress message to a TTY, suppress the usual newline
        // and write a "clear to end of line" sequence before the next message
        if ($this->IsTty) {
            if ($this->HasPendingClearLine) {
                fwrite($this->Stream, "\r" . TtyControlSequence::CLEAR_LINE . TtyControlSequence::WRAP_ON);
                $this->HasPendingClearLine = false;
            }
            if ($message[-1] === "\r") {
                fwrite($this->Stream, TtyControlSequence::WRAP_OFF . rtrim($message, "\r"));
                $this->HasPendingClearLine = true;

                return;
            }
        }

        fwrite($this->Stream, rtrim($message, "\r") . "\n");
    }

    public function getPath(): string
    {
        return $this->Path;
    }
}
