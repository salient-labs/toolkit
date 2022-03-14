<?php

declare(strict_types=1);

namespace Lkrms;

if (!class_alias("\Lkrms\Err\Err", "\Lkrms\Err"))
{
    /**
     * @ignore
     */
    class Err extends Err\Err
    {
    }
}

