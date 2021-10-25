<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use Lkrms\Console\ConsoleLevel;
use Psr\Log\LoggerInterface;

/**
 * Sends `Console` output to any PSR-3 implementor
 *
 * @package Lkrms
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

