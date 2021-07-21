<?php

declare(strict_types=1);

namespace Lkrms;
use RuntimeException;

/**
 * File-related functions
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
    public static function GetEol(string $filename) : string
    {
        $endings = [
            "\r\n",
            "\n",
        ];

        $handle = fopen($filename, 'r');

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
            if (substr($line, - strlen($eol)) == $eol)
            {
                return $eol;
            }
        }

        throw new RuntimeException("Unable to determine end-of-line sequence: $filename");
    }
}

