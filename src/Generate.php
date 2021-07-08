<?php

declare(strict_types=1);

namespace Lkrms;
use Exception;

/**
 * @package Lkrms
 */
class Generate
{
    /**
     * Generate a cryptographically secure random UUID
     *
     * Compliant with RFC4122.
     *
     * @return string
     * @throws Exception
     */
    public static function Uuid()
    {
        $bytes   = random_bytes(16);
        $uuid    = [];
        $uuid[]  = bin2hex(substr($bytes, 0, 4));
        $uuid[]  = bin2hex(substr($bytes, 4, 2));
        $uuid[]  = bin2hex(chr(ord(substr($bytes, 6, 1)) & 0xf | 0x40) . substr($bytes, 7, 1));
        $uuid[]  = bin2hex(chr(ord(substr($bytes, 8, 1)) & 0x3f | 0x80) . substr($bytes, 9, 1));
        $uuid[]  = bin2hex(substr($bytes, 10, 6));

        return implode('-', $uuid);
    }
}

