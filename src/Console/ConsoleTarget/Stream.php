<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use DateTime;
use DateTimeZone;
use Lkrms\Console\ConsoleColour;
use Lkrms\Console\ConsoleLevel;
use Lkrms\File;
use Lkrms\Test;
use RuntimeException;

/**
 * Write to a stream (e.g. a file or TTY)
 *
 * @package Lkrms
 */
class Stream extends \Lkrms\Console\ConsoleTarget
{
    private $Stream;

    /**
     * @var array
     */
    private $Levels;

    /**
     * @var bool
     */
    private $AddTimestamp;

    /**
     * @var string
     */
    private $Timestamp = "[d M H:i:s.uO] ";

    /**
     * @var DateTimeZone
     */
    private $Timezone;

    /**
     * @var bool
     */
    private $AddColour;

    /**
     * @var string
     */
    private $Path;

    /**
     * @var bool
     */
    private $IsTty;

    /**
     * @var bool
     */
    private $IsStdout;

    /**
     * @var bool
     */
    private $IsStderr;

    /**
     * Use an open stream as a console output target
     *
     * @param resource      $stream
     * @param array         $levels
     * @param bool|null     $addColour      If `null`, colour will not be added unless `$stream` is a TTY
     * @param bool|null     $addTimestamp   If `null`, timestamps will be added unless `$stream` is a TTY
     * @param string|null   $timestamp      Default: `[d M H:i:s.uO] `
     * @param string|null   $timezone       Default: as per `date_default_timezone_set` or INI setting `date.timezone`
     */
    public function __construct($stream, array $levels = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
        ConsoleLevel::DEBUG
    ], bool $addColour = null, bool $addTimestamp = null, string $timestamp = null, string $timezone = null)
    {
        stream_set_write_buffer($stream, 0);

        $this->Stream       = $stream;
        $this->Levels       = $levels;
        $this->IsTty        = stream_isatty($stream);
        $this->AddColour    = !is_null($addColour) ? $addColour : $this->IsTty;
        $this->AddTimestamp = !is_null($addTimestamp) ? $addTimestamp : !$this->IsTty;

        $this->IsStdout = Test::isSameStream($stream, STDOUT);
        $this->IsStderr = Test::isSameStream($stream, STDERR);

        if (!is_null($timestamp))
        {
            $this->Timestamp = $timestamp;
        }

        if (!is_null($timezone))
        {
            $this->Timezone = new DateTimeZone($timezone);
        }
    }

    public function IsTty(): bool
    {
        return $this->IsTty;
    }

    public function IsStdout(): bool
    {
        return $this->IsStdout;
    }

    public function IsStderr(): bool
    {
        return $this->IsStderr;
    }

    public function Reopen(string $path = null): void
    {
        if (!$this->Path)
        {
            throw new RuntimeException("Stream object not created by Stream::FromPath()");
        }

        $path = $path ?: $this->Path;

        if (!fclose($this->Stream) || !File::MaybeCreate($path, 0600) || ($stream = fopen($path, "a")) === false)
        {
            throw new RuntimeException("Could not close {$this->Path} and open $path");
        }

        $this->Stream = $stream;
        $this->Path   = $path;
    }

    public static function FromPath(string $path, array $levels = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
        ConsoleLevel::DEBUG
    ], bool $addColour = null, bool $addTimestamp = null, string $timestamp = null, string $timezone = null): Stream
    {
        if (!File::MaybeCreate($path, 0600) || ($stream = fopen($path, "a")) === false)
        {
            throw new RuntimeException("Could not open $path");
        }

        $_this       = new Stream($stream, $levels, $addColour, $addTimestamp, $timestamp, $timezone);
        $_this->Path = $path;

        return $_this;
    }

    protected function WriteToTarget(int $level, string $message, array $context)
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

    public function SetPrefix(?string $prefix): void
    {
        if ($prefix && $this->AddColour)
        {
            parent::SetPrefix(ConsoleColour::DIM . $prefix . ConsoleColour::UNDIM);
        }
        else
        {
            parent::SetPrefix($prefix);
        }
    }

    public function AddColour(): bool
    {
        return $this->AddColour;
    }

    public function Path(): string
    {
        return $this->Path;
    }
}

