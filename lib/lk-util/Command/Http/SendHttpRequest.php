<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Http;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\LkUtil\Command\Concept\Command;
use Lkrms\Support\Catalog\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Utility\Convert;
use UnexpectedValueException;

class SendHttpRequest extends Command
{
    private string $Method;

    private function getMethod(): string
    {
        if (isset($this->Method)) {
            return $this->Method;
        }

        $name = $this->nameParts();
        $this->Method = strtoupper(array_pop($name));

        return $this->Method;
    }

    public function description(): string
    {
        return "Send a {$this->getMethod()} request to an HttpSyncProvider endpoint";
    }

    protected function getOptionList(): array
    {
        $options = [
            CliOption::build()
                ->long('provider')
                ->valueName('provider')
                ->description('The HttpSyncProvider class to use')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->required(),
            CliOption::build()
                ->long('endpoint')
                ->valueName('endpoint')
                ->description("The endpoint to {$this->getMethod()}, e.g. '/posts'")
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required(),
            CliOption::build()
                ->long('query')
                ->short('q')
                ->valueName('field=value')
                ->description('A query parameter')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed(),
        ];

        if (!in_array($this->getMethod(), [HttpRequestMethod::GET, HttpRequestMethod::HEAD])) {
            $options[] = CliOption::build()
                ->long('data')
                ->short('J')
                ->valueName('file')
                ->description('The path to JSON-serialized data to submit with the request')
                ->optionType(CliOptionType::VALUE);
        }

        if (in_array($this->getMethod(), [HttpRequestMethod::GET, HttpRequestMethod::POST])) {
            $options[] = CliOption::build()
                ->long('paginate')
                ->short('P')
                ->description('Retrieve every available response page');
        }

        return $options;
    }

    protected function run(string ...$args)
    {
        /** @var class-string<HttpSyncProvider> */
        $provider = $this->getOptionValue('provider');
        /** @var HttpSyncProvider */
        $provider = $this->getProvider($provider, HttpSyncProvider::class);
        $endpoint = $this->getOptionValue('endpoint');
        $query = Convert::queryToData($this->getOptionValue('query')) ?: null;
        $data = $this->hasOption('data') ? $this->getOptionValue('data') : null;
        $data = $data ? $this->getJson($data) : null;
        $paginate = $this->hasOption('paginate') ? $this->getOptionValue('paginate') : false;

        $curler = $provider->getCurler($endpoint);

        switch ($this->getMethod()) {
            case HttpRequestMethod::GET:
                $result = $paginate ? $curler->getP($query) : $curler->get($query);
                break;

            case HttpRequestMethod::HEAD:
                $result = $curler->head($query);
                break;

            case HttpRequestMethod::POST:
                $result = $paginate ? $curler->postP($data, $query) : $curler->post($data, $query);
                break;

            case HttpRequestMethod::PUT:
                $result = $curler->put($data, $query);
                break;

            case HttpRequestMethod::DELETE:
                $result = $curler->delete($data, $query);
                break;

            case HttpRequestMethod::PATCH:
                $result = $curler->patch($data, $query);
                break;

            default:
                throw new UnexpectedValueException('Invalid method: ' . $this->getMethod());
        }

        if ($paginate) {
            /** @var iterable<array-key,mixed> $result */
            $array = Convert::iterableToArray($result);
            $result = $array;
        }

        echo json_encode($result);
    }
}
