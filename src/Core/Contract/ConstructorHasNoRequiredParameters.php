<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Can be instantiated without passing arguments to the constructor
 *
 */
interface ConstructorHasNoRequiredParameters
{
    public function __construct();
}
