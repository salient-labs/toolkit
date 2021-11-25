<?php

declare(strict_types=1);

namespace Lkrms;

if (!class_alias("\Lkrms\Curler\Curler", "\Lkrms\Curler"))
{
    /**
     * @ignore
     */
    class Curler extends Curler\Curler
    {
    }
}

