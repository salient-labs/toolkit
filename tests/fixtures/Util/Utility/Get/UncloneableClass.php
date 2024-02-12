<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Get;

class UncloneableClass
{
    private function __clone() {}
}
