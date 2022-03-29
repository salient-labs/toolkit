<?php

declare(strict_types=1);

namespace Lkrms\Util\Command\Sync;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliInvalidArgumentException;
use Lkrms\Cli\CliOptionType;
use Lkrms\Sync\Provider\HttpSyncProvider;

/**
 *
 * @package Lkrms\Util
 */
class HttpGetPath extends CliCommand
{
    public function getDescription(): string
    {
        return "Retrieve data from an HttpSyncProvider endpoint";
    }

    protected function _getName(): array
    {
        return ["http", "get"];
    }

    protected function _getOptions(): array
    {
        return [
            [
                "long"        => "provider",
                "valueName"   => "CLASS",
                "description" => "The HttpSyncProvider class to retrieve data from",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ], [
                "long"        => "path",
                "valueName"   => "PATH",
                "description" => "The endpoint to retrieve data from, e.g. '/user'",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ],
        ];
    }

    protected function run(...$args)
    {
        $providerClass = $this->getOptionValue("provider");
        $endpointPath  = $this->getOptionValue("path");

        if (!class_exists($providerClass))
        {
            throw new CliInvalidArgumentException("class does not exist: $providerClass");
        }

        $provider = new $providerClass();

        if (!($provider instanceof HttpSyncProvider))
        {
            throw new CliInvalidArgumentException("not a subclass of HttpSyncProvider: $providerClass");
        }

        $data = $provider->getCurler($endpointPath)->GetJson();

        echo json_encode($data);
    }
}

