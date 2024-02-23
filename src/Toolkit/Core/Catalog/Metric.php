<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractConvertibleEnumeration;

/**
 * Runtime performance metrics
 *
 * @extends AbstractConvertibleEnumeration<int>
 */
final class Metric extends AbstractConvertibleEnumeration
{
    public const COUNTER = 0;
    public const TIMER = 1;

    protected static $NameMap = [
        self::COUNTER => 'COUNTER',
        self::TIMER => 'TIMER',
    ];

    protected static $ValueMap = [
        'COUNTER' => self::COUNTER,
        'TIMER' => self::TIMER,
    ];
}
