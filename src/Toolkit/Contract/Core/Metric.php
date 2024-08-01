<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Runtime performance metrics
 */
interface Metric
{
    public const COUNTER = 0;
    public const TIMER = 1;
}
