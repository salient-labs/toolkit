<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractCatalog;

use Salient\Core\AbstractConvertibleEnumeration;

/**
 * @extends AbstractConvertibleEnumeration<int>
 */
class MyConvertibleEnum extends AbstractConvertibleEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;

    protected static $NameMap = [
        self::FOO => 'FOO',
        self::BAR => 'BAR',
        self::BAZ => 'BAZ',
    ];

    protected static $ValueMap = [
        'FOO' => self::FOO,
        'BAR' => self::BAR,
        'BAZ' => self::BAZ,
    ];
}
