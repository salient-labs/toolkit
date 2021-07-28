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
            if (substr($line, - strlen($eol)) == $eol)
            {
                return $eol;
            }
        }

        throw new RuntimeException("Unable to determine end-of-line sequence: $filename");
    }

    /**
     * Convert an array to CSV
     *
     * @param array $data An array of arrays (rows) to convert to CSV.
     * @param string|null $filename Path to the output file, or `null` to return
     * the CSV as a string.
     * @param bool $includeHeaderRow If `true`, include `array_keys($data[0])`
     * as a header row.
     * @return string|false|void
     * @throws RuntimeException
     */
    public static function WriteCsv(array $data, string $filename = null, $includeHeaderRow = true)
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

        if ($includeHeaderRow)
        {
            array_unshift($data, array_keys($data[0] ?? []));
        }

        foreach ($data as $row)
        {
            foreach ($row as & $value)
            {
                if ( ! is_scalar($value))
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
}

