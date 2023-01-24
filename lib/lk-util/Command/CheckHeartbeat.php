<?php declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\LkUtil\Command\Concept\Command;

class CheckHeartbeat extends Command
{
    public function getShortDescription(): string
    {
        return 'Send a heartbeat request to a provider';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('provider')
                ->valueName('PROVIDER')
                ->description('The provider to check (must implement IProvider)')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required(),
            CliOption::build()
                ->long('ttl')
                ->short('t')
                ->valueName('SECONDS')
                ->description('The time-to-live of a positive result')
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('300'),
        ];
    }

    protected function run(string ...$args)
    {
        $this->getProvider($this->getOptionValue('provider'))
             ->checkHeartbeat((int) $this->getOptionValue('ttl'));
    }
}
