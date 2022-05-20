<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Http;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Util\Env;

class HttpGetPath extends CliCommand
{
    protected function _getDescription(): string
    {
        return "Retrieve data from an HttpSyncProvider endpoint";
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "provider",
                "short"       => "i",
                "valueName"   => "CLASS",
                "description" => "The HttpSyncProvider class to retrieve data from",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ], [
                "long"        => "endpoint",
                "short"       => "e",
                "valueName"   => "PATH",
                "description" => "The endpoint to retrieve data from, e.g. '/user'",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ],
        ];
    }

    protected function _run(string ...$args)
    {
        $providerClass = $this->getOptionValue("provider") ?: Env::get("SYNC_ENTITY_PROVIDER", "");
        $endpointPath  = $this->getOptionValue("endpoint");

        if (!class_exists($providerClass) &&
            !(strpos($providerClass, "\\") === false && ($providerNamespace = Env::get("SYNC_PROVIDER_NAMESPACE", "")) &&
                class_exists($providerClass = $providerNamespace . "\\" . $providerClass)))
        {
            throw new InvalidCliArgumentException("class does not exist: $providerClass");
        }

        $provider = new $providerClass();

        if (!($provider instanceof HttpSyncProvider))
        {
            throw new InvalidCliArgumentException("not a subclass of HttpSyncProvider: $providerClass");
        }

        $data = $provider->getCurler($endpointPath)->getJson();

        echo json_encode($data);
    }
}
