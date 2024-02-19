<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractCatalog;

use Salient\Core\AbstractReflectiveEnumeration;

/**
 * @phpstan-ignore-next-line
 *
 * @extends AbstractReflectiveEnumeration<float>
 */
class MyReflectiveFloatEnum extends AbstractReflectiveEnumeration
{
    public const FOO = 0.0;
    public const BAR = 1.0;
    public const BAZ = 3.14;
}
