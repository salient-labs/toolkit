<?php

declare(strict_types=1);

namespace Lkrms\Console\ConsoleTarget;

use Lkrms\Console\ConsoleLevel;
use Psr\Log\LoggerInterface;

/**
 * Write to any PSR-3 implementor
 *
 * @package Lkrms
 */
class LoggerTarget extends ConsoleTarget
{
    /**
     * @var LoggerInterface
     */
    private $Logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->Logger = $logger;
    }

    protected function writeToTarget(int $level, string $message, array $context)
    {
        $this->Logger->log(ConsoleLevel::toPsrLogLevel($level), $message, $context);
    }
}
