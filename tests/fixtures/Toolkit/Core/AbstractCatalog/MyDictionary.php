<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractCatalog;

use Salient\Core\AbstractDictionary;

/**
 * @extends AbstractDictionary<string>
 */
class MyDictionary extends AbstractDictionary
{
    public const FOO = 'Foo';
    public const BAR = 'Bar';
    public const BAZ = 'Baz';
}
