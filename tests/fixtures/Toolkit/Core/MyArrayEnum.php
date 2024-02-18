<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\AbstractEnumeration;

/**
 * @extends AbstractEnumeration<int[]>
 */
class MyArrayEnum extends AbstractEnumeration
{
    public const FOO = [0, 1, 2];
    public const BAR = [1, 2];
    public const BAZ = [2];
}
