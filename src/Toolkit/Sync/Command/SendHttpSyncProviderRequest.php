<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Http\HttpRequestMethod as Method;
use Salient\Core\Facade\Console;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Str;
use LogicException;

/**
 * Sends HTTP requests to HTTP sync providers
 */
final class SendHttpSyncProviderRequest extends AbstractSyncCommand
{
    private string $ProviderBasename = '';
    /** @var class-string<HttpSyncProvider> */
    private string $Provider = HttpSyncProvider::class;
    private string $Endpoint = '';
    /** @var string[] */
    private array $Query = [];
    private ?string $Data = null;
    private bool $Paginate = false;
    private bool $Stream = false;

    // --

    /** @var Method::HEAD|Method::GET|Method::POST|Method::PUT|Method::DELETE|Method::PATCH */
    private string $Method;

    public function getDescription(): string
    {
        return sprintf(
            'Send a %s request to an HTTP provider',
            $this->getMethod(),
        );
    }

    protected function getOptionList(): iterable
    {
        $method = $this->getMethod();
        $builder = CliOption::build()
            ->name('provider')
            ->required();

        if ($this->HttpProviders) {
            yield $builder
                ->description('The HTTP provider to use')
                ->optionType(CliOptionType::ONE_OF_POSITIONAL)
                ->allowedValues(array_keys($this->HttpProviders))
                ->bindTo($this->ProviderBasename);
        } else {
            yield $builder
                ->description('The fully-qualified name of the HTTP provider to use')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->bindTo($this->Provider);
        }

        yield from [
            CliOption::build()
                ->name('endpoint')
                ->description("The endpoint to request, e.g. '/posts'")
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->Endpoint),
            CliOption::build()
                ->long('query')
                ->short('q')
                ->valueName('field=value')
                ->description('A query parameter to apply to the request')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Query),
        ];

        if (!($method === Method::HEAD || $method === Method::GET)) {
            yield CliOption::build()
                ->long('data')
                ->short('J')
                ->valueName('file')
                ->description('The path to JSON-serialized data to submit with the request')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE_OR_DASH)
                ->bindTo($this->Data);
        }

        if ($method === Method::GET || $method === Method::POST) {
            yield from [
                CliOption::build()
                    ->long('paginate')
                    ->short('P')
                    ->description('Use pagination to iterate over the response')
                    ->bindTo($this->Paginate),
                CliOption::build()
                    ->long('stream')
                    ->short('s')
                    ->description('Output a stream of entities when pagination is used')
                    ->bindTo($this->Stream),
            ];
        }
    }

    protected function run(string ...$args)
    {
        Console::registerStderrTarget();

        if ($this->HttpProviders) {
            $provider = $this->HttpProviders[$this->ProviderBasename];
        } else {
            $provider = $this->Provider;

            if (!is_a(
                $this->App->getName($provider),
                HttpSyncProvider::class,
                true,
            )) {
                throw new CliInvalidArgumentsException(sprintf(
                    '%s does not inherit %s',
                    $provider,
                    HttpSyncProvider::class,
                ));
            }

            if (!$this->App->has($provider)) {
                $this->App->singleton($provider);
            }
        }

        $provider = $this->App->get($provider);
        $query = Get::filter($this->Query);
        $data = $this->Data !== null
            ? $this->getJson($this->Data, false)
            : null;

        $curler = $provider->getCurler($this->Endpoint);
        if ($this->Paginate && $curler->getPager() === null) {
            throw new CliInvalidArgumentsException(sprintf(
                '%s does not support pagination',
                $provider->getName(),
            ));
        }

        switch ($this->getMethod()) {
            case Method::HEAD:
                $result = $curler->head($query);
                break;

            case Method::GET:
                $result = $this->Paginate
                    ? $curler->getP($query)
                    : $curler->get($query);
                break;

            case Method::POST:
                $result = $this->Paginate
                    ? $curler->postP($data, $query)
                    : $curler->post($data, $query);
                break;

            case Method::PUT:
                $result = $curler->put($data, $query);
                break;

            case Method::DELETE:
                $result = $curler->delete($data, $query);
                break;

            case Method::PATCH:
                $result = $curler->patch($data, $query);
                break;
        }

        if (!$this->Paginate) {
            echo Json::prettyPrint($result) . \PHP_EOL;
            return;
        }

        /** @var iterable<mixed> $result */
        $count = 0;

        if ($this->Stream) {
            foreach ($result as $entity) {
                $count++;
                echo Json::prettyPrint($entity) . \PHP_EOL;
            }
        } else {
            $indent = '    ';
            foreach ($result as $entity) {
                if (!$count++) {
                    echo '[' . \PHP_EOL;
                } else {
                    echo ',' . \PHP_EOL;
                }
                echo $indent . Json::prettyPrint($entity, 0, \PHP_EOL . $indent);
            }
            if ($count) {
                echo \PHP_EOL . ']' . \PHP_EOL;
            } else {
                echo '[]' . \PHP_EOL;
            }
        }

        Console::summary(Inflect::format(
            $count,
            '{{#}} {{#:entity}} retrieved',
        ));
    }

    /**
     * @return Method::HEAD|Method::GET|Method::POST|Method::PUT|Method::DELETE|Method::PATCH
     */
    private function getMethod(): string
    {
        if (isset($this->Method)) {
            return $this->Method;
        }

        $method = Str::upper((string) Arr::last($this->getNameParts()));
        if (!(
            $method === Method::HEAD
            || $method === Method::GET
            || $method === Method::POST
            || $method === Method::PUT
            || $method === Method::DELETE
            || $method === Method::PATCH
        )) {
            throw new LogicException(sprintf('Invalid method: %s', $method));
        }

        return $this->Method = $method;
    }
}
