<?php

declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Http;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOptionType;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Facade\Env;
use Lkrms\Sync\Provider\HttpSyncProvider;
use UnexpectedValueException;

class SendHttpRequest extends CliCommand
{
    private $Method;

    private function getMethod()
    {
        if ($this->Method)
        {
            return $this->Method;
        }

        $name = $this->getName();
        return $this->Method = strtoupper(array_pop($name));
    }

    protected function _getDescription(): string
    {
        return "Send a {$this->getMethod()} request to an HttpSyncProvider endpoint";
    }

    protected function _getOptions(): array
    {
        $options = [
            [
                "long"        => "provider",
                "short"       => "i",
                "valueName"   => "CLASS",
                "description" => "The HttpSyncProvider class to use",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
                "env"         => "DEFAULT_PROVIDER",
            ],
            [
                "long"        => "endpoint",
                "short"       => "e",
                "valueName"   => "ENDPOINT",
                "description" => "The endpoint to {$this->getMethod()}, e.g. '/posts'",
                "optionType"  => CliOptionType::VALUE,
                "required"    => true,
            ],
            [
                "long"            => "query",
                "short"           => "q",
                "valueName"       => "FIELD=VALUE",
                "description"     => "A query parameter (may be used more than once)",
                "optionType"      => CliOptionType::VALUE,
                "multipleAllowed" => true,
            ],
        ];

        switch ($this->getMethod())
        {
            case "GET":
            case "HEAD":
                break;

            default:
                $options[] = [
                    "long"        => "json",
                    "short"       => "j",
                    "valueName"   => "FILE",
                    "description" => "The path to JSON-serialized data to submit with the request",
                    "optionType"  => CliOptionType::VALUE,
                ];
                break;
        }

        return $options;
    }

    protected function run(string ...$args)
    {
        $providerClass = $this->getOptionValue("provider");
        $endpointPath  = $this->getOptionValue("endpoint");
        $query         = $this->getOptionValue("query");
        $json          = $this->hasOption("json") ? $this->getOptionValue("json") : null;

        $query = array_filter(
            array_combine(
                array_map(fn($param) => explode("=", $param, 2)[0], $query),
                array_map(fn($param) => explode("=", $param, 2)[1] ?? null, $query)
            ),
            fn($value, $field) => trim($field) && !is_null($value),
            ARRAY_FILTER_USE_BOTH
        ) ?: null;

        if ($json)
        {
            if ($json == "-")
            {
                $json = "php://stdin";
            }
            elseif (!file_exists($json))
            {
                throw new InvalidCliArgumentException("file not found: $json");
            }
            $data = json_decode(file_get_contents($json), true);
        }

        if (!class_exists($providerClass) &&
            !(strpos($providerClass, "\\") === false &&
                ($providerNamespace         = Env::get("PROVIDER_NAMESPACE", "")) &&
                class_exists($providerClass = $providerNamespace . "\\" . $providerClass)))
        {
            throw new InvalidCliArgumentException("class does not exist: $providerClass");
        }

        $provider = $this->app()->get($providerClass);

        if (!($provider instanceof HttpSyncProvider))
        {
            throw new InvalidCliArgumentException("not a subclass of HttpSyncProvider: $providerClass");
        }

        $curler = $provider->getCurler($endpointPath);

        switch ($this->getMethod())
        {
            case "GET":
                $result = $curler->getJson($query);
                break;

            case "HEAD":
                $result = $curler->head($query);
                break;

            case "POST":
                $result = $curler->postJson($data ?? null, $query);
                break;

            case "PUT":
                $result = $curler->putJson($data ?? null, $query);
                break;

            case "DELETE":
                $result = $curler->deleteJson($data ?? null, $query);
                break;

            case "PATCH":
                $result = $curler->patchJson($data ?? null, $query);
                break;

            default:
                throw new UnexpectedValueException("Invalid method: " . $this->getMethod());
        }

        echo json_encode($result);
    }
}
