<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Composer\Autoload\ClassLoader;
use Lkrms\Core\Utility;
use RuntimeException;

/**
 * Work with files, directories and paths
 *
 */
final class File extends Utility
{
    /**
     * Get a file's end-of-line sequence
     *
     * @param string $filename
     * @return string|false `"\r\n"` or `"\n"` on success, or `false` if the
     * file's line endings couldn't be determined.
     */
    public static function getEol(string $filename)
    {
        if (($handle = fopen($filename, "r")) === false ||
            ($line   = fgets($handle)) === false ||
            fclose($handle) === false)
        {
            return false;
        }

        foreach (["\r\n", "\n"] as $eol)
        {
            if (substr($line, -strlen($eol)) == $eol)
            {
                return $eol;
            }
        }

        return false;
    }

    /**
     * Create a file if it doesn't exist
     *
     * @param string $filename Full path to the file.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @param int $dirPermissions Only used if `$filename`'s parent directory
     * needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public static function maybeCreate(
        string $filename,
        int $permissions    = 0777,
        int $dirPermissions = 0777
    ): bool
    {
        $dir = dirname($filename);

        if ((file_exists($dir) || mkdir($dir, $dirPermissions, true)) &&
            (file_exists($filename) || (touch($filename) && chmod($filename, $permissions))))
        {
            return true;
        }

        return false;
    }

    /**
     * Create a directory if it doesn't exist
     *
     * @param string $filename Full path to the directory.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public static function maybeCreateDirectory(
        string $filename,
        int $permissions = 0777
    ): bool
    {
        if (file_exists($filename) || mkdir($filename, $permissions, true))
        {
            return true;
        }

        return false;
    }

    /**
     * Get the URI or filename associated with a stream
     *
     * @param resource $stream Any stream created by `fopen()`, `fsockopen()`,
     * `pfsockopen()` or `stream_socket_client()`.
     * @return null|string `null` if `$stream` is not an open stream resource.
     */
    public static function getStreamUri($stream): ?string
    {
        if (is_resource($stream) && get_resource_type($stream) == "stream")
        {
            return stream_get_meta_data($stream)["uri"];
        }

        return null;
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
    public static function writeCsv(
        array $data,
        string $filename  = null,
        bool $headerRow   = true,
        string $nullValue = null
    ) {
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
            foreach ($row as &$value)
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
     * Return the name of a file unique to the current script and user
     *
     * Unlike with `tempnam()`, nothing is created on the filesystem.
     *
     * @param string $suffix
     * @param string|null $dir If null, `sys_get_temp_dir()` is used.
     * @return string
     */
    public static function getStablePath(string $suffix = ".log", string $dir = null)
    {
        $basename = basename($_SERVER["SCRIPT_FILENAME"]);
        $hash     = Generate::hash(realpath($_SERVER["SCRIPT_FILENAME"]));
        $euid     = posix_geteuid();

        return (is_null($dir)
            ? realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR
            : ($dir ? rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : ""))
        . "$basename-$hash-$euid$suffix";
    }

    /**
     * Use ClassLoader to find the path to the file where a class is defined
     *
     * @param string $class
     * @return null|string
     */
    public static function getClassPath(string $class): ?string
    {
        $class = trim($class, "\\");

        foreach (ClassLoader::getRegisteredLoaders() as $loader)
        {
            if ($file = $loader->findFile($class))
            {
                return $file;
            }
        }

        return null;
    }

    /**
     * Use ClassLoader's PSR-4 prefixes to resolve a namespace to a path
     *
     * @param string $namespace
     * @return null|string
     */
    public static function getNamespacePath(string $namespace): ?string
    {
        $namespace = trim($namespace, "\\");
        $prefixes  = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader)
        {
            $prefixes = array_merge($prefixes, $loader->getPrefixesPsr4());
        }

        uksort($prefixes, function ($p1, $p2)
        {
            $l1 = strlen($p1);
            $l2 = strlen($p2);

            return ($l1 === $l2) ? 0 : ($l1 < $l2 ? 1 : - 1);
        });

        foreach ($prefixes as $prefix => $dirs)
        {
            if (substr($namespace . "\\", 0, strlen($prefix)) == $prefix)
            {
                foreach (Convert::toArray($dirs) as $dir)
                {
                    if (($dir = realpath($dir)) && is_dir($dir))
                    {
                        if ($subdir = strtr(substr($namespace, strlen($prefix)), "\\", DIRECTORY_SEPARATOR))
                        {
                            return $dir . DIRECTORY_SEPARATOR . $subdir;
                        }

                        return $dir;
                    }
                }
            }
        }

        return null;
    }
}
