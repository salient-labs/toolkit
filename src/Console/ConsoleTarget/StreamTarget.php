<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use DateTime;
use DateTimeZone;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Facade\File;
use RuntimeException;

/**
 * Write console messages to a stream (e.g. a file or TTY)
 *
 */
class StreamTarget extends ConsoleTarget
{
    /**
     * @var resource
     */
    private $Stream;

    /**
     * @var int[]
     */
    private $Levels;

    /**
     * @var bool
     */
    private $AddTimestamp;

    /**
     * @var string
     */
    private $Timestamp = "[d M y H:i:s.vO] ";

    /**
     * @var DateTimeZone
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
     * Use an open stream as a console output target
     *
     * @param resource $stream
     * @param int[] $levels
     * @param bool|null $addTimestamp If `null`, timestamps will be added unless
     * `$stream` is a TTY
     * @param string|null $timestamp Default: `[d M y H:i:s.vO] `
     * @param string|null $timezone Default: as per `date_default_timezone_set`
     * or INI setting `date.timezone`
     */
    public function __construct(
        $stream,
        array $levels      = ConsoleLevels::ALL,
        bool $addTimestamp = null,
        string $timestamp  = null,
        string $timezone   = null
    ) {
        stream_set_write_buffer($stream, 0);

        $this->Stream       = $stream;
        $this->Levels       = $levels;
        $this->IsStdout     = File::getStreamUri($stream) == "php://stdout";
        $this->IsStderr     = File::getStreamUri($stream) == "php://stderr";
        $this->IsTty        = stream_isatty($stream);
        $this->AddTimestamp = !is_null($addTimestamp) ? $addTimestamp : !$this->IsTty;

        if (!is_null($timestamp))
        {
            $this->Timestamp = $timestamp;
        }

        if (!is_null($timezone))
        {
            $this->Timezone = new DateTimeZone($timezone);
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
        if (!$this->Path)
        {
            throw new RuntimeException("StreamTarget not created by StreamTarget::fromPath()");
        }

        $path = $path ?: $this->Path;

        if (!fclose($this->Stream) || !File::maybeCreate($path, 0600) || ($stream = fopen($path, "a")) === false)
        {
            throw new RuntimeException("Could not close {$this->Path} and open $path");
        }

        $this->Stream = $stream;
        $this->Path   = $path;
    }

    /**
     * Open a file in append mode and return a console output target for it
     *
     * @param string $path
     * @param int[] $levels
     * @param bool|null $addTimestamp If `null`, timestamps will be added unless
     * `$stream` is a TTY
     * @param string|null $timestamp Default: `[d M y H:i:s.vO] `
     * @param string|null $timezone Default: as per `date_default_timezone_set`
     * or INI setting `date.timezone`
     * @return StreamTarget
     */
    public static function fromPath(
        string $path,
        array $levels      = ConsoleLevels::ALL,
        bool $addTimestamp = null,
        string $timestamp  = null,
        string $timezone   = null
    ): StreamTarget
    {
        if (!File::maybeCreate($path, 0600) || ($stream = fopen($path, "a")) === false)
        {
            throw new RuntimeException("Could not open $path");
        }

        $stream       = new StreamTarget($stream, $levels, $addTimestamp, $timestamp, $timezone);
        $stream->Path = $path;

        return $stream;
    }

    protected function writeToTarget(int $level, string $message, array $context)
    {
        if (in_array($level, $this->Levels))
        {
            if ($this->AddTimestamp)
            {
                $now     = (new DateTime("now", $this->Timezone))->format($this->Timestamp);
                $message = $now . str_replace("\n", "\n" . $now, $message);
            }

            // Don't add a newline if $message has a trailing carriage return
            // (e.g. when a progress bar is being displayed)
            fwrite($this->Stream, $message . (substr($message, -1) == "\r" ? "" : "\n"));
        }
    }

    public function getPath(): string
    {
        return $this->Path;
    }
}
