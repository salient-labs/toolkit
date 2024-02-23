<?php declare(strict_types=1);

namespace Salient\Console\Contract;

use Salient\Console\Support\ConsoleTagFormats as TagFormats;

interface ConsoleTagFormatFactory
{
    /**
     * Get an object that maps inline formatting tags to formats
     */
    public static function getTagFormats(): TagFormats;
}
