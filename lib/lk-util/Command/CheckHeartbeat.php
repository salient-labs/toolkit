<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Contract\IProvider;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Util\Env;

class CheckHeartbeat extends CliCommand
{
    protected function _getDescription(): string
    {
        return "Send a heartbeat request to a provider";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "provider",
                "short"       => "i",
                "valueName"   => "CLASS",
                "description" => "The provider to check (must implement IProvider)",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
                "env"         => "DEFAULT_PROVIDER",
            ],
            [
                "long"         => "ttl",
                "short"        => "t",
                "valueName"    => "SECONDS",
                "description"  => "The time-to-live of a positive result",
                "optionType"   => CliOptionType::VALUE,
                "defaultValue" => "300",
            ],
        ];
    }

    protected function run(string ...$args)
    {
        $providerClass = $this->getOptionValue("provider");

        if (!class_exists($providerClass) &&
            !(strpos($providerClass, "\\") === false &&
                ($providerNamespace         = Env::get("PROVIDER_NAMESPACE", "")) &&
                class_exists($providerClass = $providerNamespace . "\\" . $providerClass)))
        {
            throw new InvalidCliArgumentException("class does not exist: $providerClass");
        }

        $provider = $this->container()->get($providerClass);

        if (!($provider instanceof IProvider))
        {
            throw new InvalidCliArgumentException("not a subclass of IProvider: $providerClass");
        }

        $provider->checkHeartbeat((int)$this->getOptionValue("ttl"));
    }
}
