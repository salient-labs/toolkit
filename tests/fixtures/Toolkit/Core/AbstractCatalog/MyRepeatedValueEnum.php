<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractCatalog;

use Salient\Core\AbstractEnumeration;

/**
 * @extends AbstractEnumeration<int>
 */
class MyRepeatedValueEnum extends AbstractEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;
    public const QUX = 2;
}
