<?php

declare(strict_types=1);

namespace Lkrms\Tests\Runtime;

use Lkrms\Runtime;

function getCaller($depth)
{
    return Runtime::getCaller($depth);
}

function getCallerViaFunction($depth = 0)
{
    return getCaller($depth);
}

