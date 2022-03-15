<?php

declare(strict_types=1);

namespace Lkrms;

use RuntimeException;

/**
 * Path/file helpers
 *
 * @package Lkrms
 */
class File
{
    /**
     * Get a file's end-of-line sequence
     *
     * @param string $filename Path to the file to check.
     * @return string Either "\r\n" or "\n".
     * @throws RuntimeException
     */
    public static function GetEol(string $filename): string
    {
        $endings = [
            "\r\n",
            "\n",
        ];

        $handle = fopen($filename, "r");

        if ($handle === false)
        {
            throw new RuntimeException("Could not open $filename");
        }

        $line = fgets($handle);
        fclose($handle);

        if ($line === false)
        {
            throw new RuntimeException("Could not read $filename");
        }

        foreach ($endings as $eol)
        {
            if (substr($line, -strlen($eol)) == $eol)
            {
                return $eol;
            }
        }

        throw new RuntimeException("Unable to determine end-of-line sequence: $filename");
    }

    /**
     * Create a file if it doesn't exist
     *
     * `$permissions` and `$dirPermissions` are only used if the file and/or its parent directory don't exist.
     *
     * @param string $filename Full path to the file.
     * @param int $permissions
     * @param int $dirPermissions
     * @return bool
     */
    public static function MaybeCreate(string $filename, int $permissions = 0777, int $dirPermissions = 0777): bool
    {
        $dir = dirname($filename);

        if (!file_exists($dir))
        {
            if (!mkdir($dir, $dirPermissions, true))
            {
                throw new RuntimeException("Could not create directory $dir");
            }
        }

        if (!file_exists($filename))
        {
            if (!touch($filename) || !chmod($filename, $permissions))
            {
                throw new RuntimeException("Could not create file $filename");
            }
        }

        return true;
    }

    /**
     * Convert an array to CSV
     *
     * @param array $data An array of arrays (rows) to convert to CSV.
     * @param string|null $filename Path to the output file, or `null` to return
     * the CSV as a string.
     * @param bool $headerRow If `true`, include `array_keys($data[0])` as a
     * header row.
     * @param string $nullValue What should appear in cells that are `null`?
     * @return string|false|void
     * @throws RuntimeException
     */
    public static function WriteCsv(
        array $data,
        string $filename  = null,
        bool $headerRow   = true,
        string $nullValue = null
    )
    {
        $return = false;

        if (is_null($filename))
        {
            $filename = "php://temp";
            $return   = true;
        }

        $f = fopen($filename, "w");

        if ($f === false)
        {
            throw new RuntimeException("Could not open $filename");
        }

        if ($headerRow)
        {
            array_unshift($data, array_keys($data[array_keys($data)[0]] ?? []));
        }

        foreach ($data as $row)
        {
            foreach ($row as & $value)
            {
                if (is_null($value))
                {
                    $value = $nullValue;
                }
                elseif (!is_scalar($value))
                {
                    $value = json_encode($value);
                }
            }

            if (fputcsv($f, $row) === false)
            {
                throw new RuntimeException("Could not write to $filename");
            }
        }

        if ($return)
        {
            rewind($f);
            $csv = stream_get_contents($f);
            fclose($f);

            return $csv;
        }
        else
        {
            fclose($f);
        }
    }

    /**
     * Get a pathname relative to a parent directory
     *
     * @param string $childPath Path to a child of `$parentPath` (must exist).
     * @param string $parentPath Path to an ancestor of `$childPath`.
     * @return false|string
     * @throws RuntimeException
     */
    public static function GetChildPathRelative(string $childPath, string $parentPath)
    {
        $file = realpath($childPath);
        $dir  = realpath($parentPath);

        if ($file === false || $dir === false)
        {
            return false;
        }

        if ($file == $dir || strpos($file, $dir) !== 0)
        {
            throw new RuntimeException("$childPath is not a descendant of $parentPath");
        }

        return substr($file, strlen($dir) + 1);
    }

    /**
     * Return the name of a file unique to the current script and user
     *
     * Unlike with `tempnam()`, nothing is created on the filesystem.
     *
     * @param string $suffix
     * @param string|null $dir If null, `sys_get_temp_dir()` is used.
     * @return string
     */
    public static function StablePath(string $suffix = ".log", string $dir = null)
    {
        $basename = basename($_SERVER["SCRIPT_FILENAME"]);
        $hash     = Convert::hash(realpath($_SERVER["SCRIPT_FILENAME"]));
        $euid     = posix_geteuid();

        return (is_null($dir)
            ? realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR
            : ($dir ? rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : ""))
        . "$basename-$hash-$euid$suffix";
    }
}

