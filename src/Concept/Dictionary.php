<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IDictionary;

/**
 * Base class for dictionaries
 *
 */
abstract class Dictionary implements IDictionary
{
    final private function __construct() {}
}
