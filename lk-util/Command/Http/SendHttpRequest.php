<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Http;

use Lkrms\Http\Catalog\HttpRequestMethod;
use Lkrms\LkUtil\Command\Concept\Command;
use Salient\Cli\Catalog\CliOptionType;
use Salient\Cli\Catalog\CliOptionValueType;
use Salient\Cli\CliOption;
use Salient\Core\Exception\UnexpectedValueException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Str;
use Salient\Sync\Concept\HttpSyncProvider;

/**
 * Sends HTTP requests to HTTP sync providers
 */
final class SendHttpRequest extends Command
{
    /**
     * @var class-string<HttpSyncProvider>
     */
    private string $Provider = HttpSyncProvider::class;

    private string $HttpEndpoint = '';

    /**
     * @var string[]
     */
    private array $HttpQuery = [];

    private ?string $HttpDataFile = null;

    private bool $Paginate = false;

    // --

    /**
     * @var HttpRequestMethod::*
     */
    private string $HttpMethod;

    private function getMethod(): string
    {
        return $this->HttpMethod
            ?? ($this->HttpMethod = Str::upper(Arr::last($this->nameParts())));
    }

    public function description(): string
    {
        return sprintf(
            'Send a %s request to an HTTP sync provider endpoint',
            $this->getMethod()
        );
    }

    protected function getOptionList(): array
    {
        $options = [
            CliOption::build()
                ->long('provider')
                ->short('p')
                ->valueName('provider')
                ->description('The HttpSyncProvider class to use')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueCallback(fn(string $value) => $this->getFqcnOptionValue($value))
                ->required()
                ->bindTo($this->Provider),
            CliOption::build()
                ->long('endpoint')
                ->short('e')
                ->valueName('endpoint')
                ->description("The endpoint to {$this->getMethod()}, e.g. '/posts'")
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->HttpEndpoint),
            CliOption::build()
                ->long('query')
                ->short('q')
                ->valueName('field=value')
                ->description('A query parameter')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->HttpQuery),
        ];

        if (!in_array($this->getMethod(), [HttpRequestMethod::GET, HttpRequestMethod::HEAD])) {
            $options[] = CliOption::build()
                ->long('data')
                ->short('J')
                ->valueName('file')
                ->description('The path to JSON-serialized data to submit with the request')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE)
                ->bindTo($this->HttpDataFile);
        }

        if (in_array($this->getMethod(), [HttpRequestMethod::GET, HttpRequestMethod::POST])) {
            $options[] = CliOption::build()
                ->long('paginate')
                ->short('P')
                ->description('Retrieve every available response page')
                ->bindTo($this->Paginate);
        }

        return $options;
    }

    protected function run(string ...$args)
    {
        /** @var HttpSyncProvider */
        $provider = $this->getProvider($this->Provider, HttpSyncProvider::class);
        $query = Get::filter($this->HttpQuery) ?: null;
        $data = ($this->HttpDataFile ?? null) === null
            ? null
            : $this->getJson($this->HttpDataFile, $dataUri, false);
        $this->Paginate = $this->Paginate ?? false;

        $curler = $provider->getCurler($this->HttpEndpoint);

        switch ($this->getMethod()) {
            case HttpRequestMethod::GET:
                $result = $this->Paginate ? $curler->getP($query) : $curler->get($query);
                break;

            case HttpRequestMethod::HEAD:
                $result = $curler->head($query);
                break;

            case HttpRequestMethod::POST:
                $result = $this->Paginate ? $curler->postP($data, $query) : $curler->post($data, $query);
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

        if ($this->Paginate) {
            /** @var iterable<array-key,mixed> $result */
            $array = Get::array($result);
            $result = $array;
        }

        echo Json::prettyPrint($result) . \PHP_EOL;
    }
}
