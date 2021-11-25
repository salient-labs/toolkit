<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use Lkrms\Console\ConsoleLevel;
use Psr\Log\LoggerInterface;

/**
 * Write to any PSR-3 implementor
 *
 * @package Lkrms\Console
 */
class Logger extends \Lkrms\Console\ConsoleTarget
{
    /**
     * @var LoggerInterface
     */
    private $Logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->Logger = $logger;
    }

    protected function WriteToTarget(int $level, string $message, array $context)
    {
        $this->Logger->log(ConsoleLevel::ToPsrLogLevel($level), $message, $context);
    }
}

