<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\ConvertibleEnumeration;

/**
 * Runtime performance metrics
 *
 * @extends ConvertibleEnumeration<int>
 */
final class Metric extends ConvertibleEnumeration
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
