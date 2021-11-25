<?php

declare(strict_types=1);

namespace Lkrms;

if (!class_alias("\Lkrms\Err", "\Lkrms\Error"))
{
    /**
     * @ignore
     */
    class Error extends Err
    {
    }
}

