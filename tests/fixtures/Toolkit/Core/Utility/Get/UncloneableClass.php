<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

class UncloneableClass
{
    private function __clone() {}
}
