<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\AbstractReflectiveEnumeration;

/**
 * @extends AbstractReflectiveEnumeration<int>
 */
class MyInvalidEnum extends AbstractReflectiveEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;
    public const QUX = 2;
}
