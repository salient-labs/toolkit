<?php

declare(strict_types=1);

namespace Lkrms\Cli;

use Lkrms\Assert;

/**
 * CLI-specific assertions
 *
 * @package Lkrms
 */
class CliAssert
{
    public static function QualifiedNameIsValid(?array $nameParts)
    {
        Assert::NotEmpty($nameParts, "nameParts");

        foreach ($nameParts as $i => $name)
        {
            Assert::PregMatch($name, '/^[a-zA-Z][a-zA-Z0-9_-]*$/', "nameParts[$i]");
        }
    }
}

