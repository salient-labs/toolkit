<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\Catalog\ConsoleLevel;
use Lkrms\Console\Concept\ConsoleTarget;
use Psr\Log\LoggerInterface;

/**
 * Write console messages to a PSR-3 implementor
 *
 */
final class LoggerTarget extends ConsoleTarget
{
    /**
     * @var LoggerInterface
     */
    private $Logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->Logger = $logger;
    }

    protected function writeToTarget(int $level, string $message, array $context): void
    {
        $this->Logger->log(ConsoleLevel::toPsrLogLevel($level), $message, $context);
    }
}
