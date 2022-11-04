<?php

declare(strict_types=1);

/**
 * @package Lkrms\LkUtil
 */

namespace Lkrms\LkUtil\Command\Http;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Support\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
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

        $name = $this->getNameParts();
        return $this->Method = strtoupper(array_pop($name));
    }

    public function getDescription(): string
    {
        return "Send a {$this->getMethod()} request to an HttpSyncProvider endpoint";
    }

    protected function getOptionList(): array
    {
        $options = [
            (CliOption::build()
                ->long("provider")
                ->short("i")
                ->valueName("CLASS")
                ->description("The HttpSyncProvider class to use")
                ->optionType(CliOptionType::VALUE)
                ->required()
                ->envVariable("DEFAULT_PROVIDER")
                ->go()),
            (CliOption::build()
                ->long("endpoint")
                ->short("e")
                ->valueName("ENDPOINT")
                ->description("The endpoint to {$this->getMethod()}, e.g. '/posts'")
                ->optionType(CliOptionType::VALUE)
                ->required()
                ->go()),
            (CliOption::build()
                ->long("query")
                ->short("q")
                ->valueName("FIELD=VALUE")
                ->description("A query parameter (may be used more than once)")
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->go()),
        ];

        if (in_array($this->getMethod(), [HttpRequestMethod::GET, HttpRequestMethod::POST]))
        {
            $options[] = (CliOption::build()
                ->long("paginate")
                ->short("p")
                ->description("Retrieve every available response page")
                ->go());
        }

        if (!in_array($this->getMethod(), [HttpRequestMethod::GET, HttpRequestMethod::HEAD]))
        {
            $options[] = (CliOption::build()
                ->long("json")
                ->short("j")
                ->valueName("FILE")
                ->description("The path to JSON-serialized data to submit with the request")
                ->optionType(CliOptionType::VALUE)
                ->go());
        }

        return $options;
    }

    protected function run(string ...$args)
    {
        $providerClass = $this->getOptionValue("provider");
        $endpointPath  = $this->getOptionValue("endpoint");
        $query         = $this->getOptionValue("query");
        $paginate      = $this->hasOption("paginate") ? $this->getOptionValue("paginate") : false;
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
                throw new CliArgumentsInvalidException("file not found: $json");
            }
            $data = json_decode(file_get_contents($json), true);
        }

        if (!class_exists($providerClass) &&
            !(strpos($providerClass, "\\") === false &&
                ($providerNamespace         = Env::get("PROVIDER_NAMESPACE", "")) &&
                class_exists($providerClass = $providerNamespace . "\\" . $providerClass)))
        {
            throw new CliArgumentsInvalidException("class does not exist: $providerClass");
        }

        $provider = $this->app()->get($providerClass);

        if (!($provider instanceof HttpSyncProvider))
        {
            throw new CliArgumentsInvalidException("not a subclass of HttpSyncProvider: $providerClass");
        }

        $curler = $provider->getCurler($endpointPath);

        switch ($this->getMethod())
        {
            case HttpRequestMethod::GET:
                $result = $paginate ? $curler->getP($query) : $curler->get($query);
                break;

            case HttpRequestMethod::HEAD:
                $result = $curler->head($query);
                break;

            case HttpRequestMethod::POST:
                $result = $paginate ? $curler->postP($data ?? null, $query) : $curler->post($data ?? null, $query);
                break;

            case HttpRequestMethod::PUT:
                $result = $curler->put($data ?? null, $query);
                break;

            case HttpRequestMethod::DELETE:
                $result = $curler->delete($data ?? null, $query);
                break;

            case HttpRequestMethod::PATCH:
                $result = $curler->patch($data ?? null, $query);
                break;

            default:
                throw new UnexpectedValueException("Invalid method: " . $this->getMethod());
        }

        if ($paginate)
        {
            $result = Convert::iterableToArray($result);
        }

        echo json_encode($result);
    }
}
