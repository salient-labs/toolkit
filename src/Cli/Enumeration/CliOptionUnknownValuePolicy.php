<?php declare(strict_types=1);

namespace Lkrms\Cli\Enumeration;

use Lkrms\Concept\Enumeration;

/**
 * Unknown value policy for command line options of type ONE_OF, ONE_OF_OPTIONAL
 * and ONE_OF_POSITIONAL
 *
 */
final class CliOptionUnknownValuePolicy extends Enumeration
{
    /**
     * Throw exceptions over unknown values
     *
     */
    public const REJECT = 0;

    /**
     * Silently discard unknown values
     *
     */
    public const DISCARD = 1;

    /**
     * Silently accept unknown values
     *
     */
    public const ACCEPT = 2;
}
