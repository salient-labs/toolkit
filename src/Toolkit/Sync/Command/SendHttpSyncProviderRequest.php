<?php declare(strict_types=1);

namespace Salient\Sync\Command;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliOption;
use Salient\Cli\CliUtil;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Http\HasRequestMethod;
use Salient\Core\Facade\Console;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Sends HTTP requests to HTTP sync providers
 */
final class SendHttpSyncProviderRequest extends AbstractSyncCommand implements HasRequestMethod
{
    private string $ProviderBasename = '';
    /** @var class-string */
    private string $Provider = HttpSyncProvider::class;
    private string $Endpoint = '';
    /** @var string[] */
    private array $Query = [];
    private ?string $Data = null;
    private bool $Paginate = false;
    private bool $Stream = false;

    // --

    /** @var self::METHOD_HEAD|self::METHOD_GET|self::METHOD_POST|self::METHOD_PUT|self::METHOD_DELETE|self::METHOD_PATCH */
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

        if (!($method === self::METHOD_HEAD || $method === self::METHOD_GET)) {
            yield CliOption::build()
                ->long('data')
                ->short('J')
                ->valueName('file')
                ->description('The path to JSON-serialized data to submit with the request')
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE_OR_DASH)
                ->bindTo($this->Data);
        }

        if ($method === self::METHOD_GET || $method === self::METHOD_POST) {
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

        yield from $this->getGlobalOptionList();
    }

    // @phpstan-ignore return.unusedType
    protected function run(string ...$args)
    {
        $this->startRun();

        if ($this->HttpProviders) {
            $provider = $this->HttpProviders[$this->ProviderBasename];
        } else {
            $provider = $this->Provider;

            if (!is_a(
                $this->App->getClass($provider),
                HttpSyncProvider::class,
                true,
            )) {
                throw new CliInvalidArgumentsException(sprintf(
                    '%s does not inherit %s',
                    $provider,
                    HttpSyncProvider::class,
                ));
            }

            /** @var class-string<HttpSyncProvider> $provider */
            if (!$this->App->has($provider)) {
                $this->App->singleton($provider);
            }
        }

        $provider = $this->App->get($provider);

        try {
            $query = Get::filter($this->Query);
        } catch (InvalidArgumentException $ex) {
            throw new CliInvalidArgumentsException(sprintf(
                'invalid query (%s)',
                $ex->getMessage(),
            ));
        }

        $data = $this->Data !== null
            ? CliUtil::getJson($this->Data, false)
            : null;

        $curler = $provider->getCurler($this->Endpoint);
        if ($this->Paginate && $curler->getPager() === null) {
            throw new CliInvalidArgumentsException(sprintf(
                '%s does not support pagination',
                $provider->getName(),
            ));
        }

        switch ($this->getMethod()) {
            case self::METHOD_HEAD:
                $result = $curler->head($query);
                break;

            case self::METHOD_GET:
                $result = $this->Paginate
                    ? $curler->getP($query)
                    : $curler->get($query);
                break;

            case self::METHOD_POST:
                $result = $this->Paginate
                    ? $curler->postP($data, $query)
                    : $curler->post($data, $query);
                break;

            case self::METHOD_PUT:
                $result = $curler->put($data, $query);
                break;

            case self::METHOD_DELETE:
                $result = $curler->delete($data, $query);
                break;

            case self::METHOD_PATCH:
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
     * @return self::METHOD_HEAD|self::METHOD_GET|self::METHOD_POST|self::METHOD_PUT|self::METHOD_DELETE|self::METHOD_PATCH
     */
    private function getMethod(): string
    {
        if (isset($this->Method)) {
            return $this->Method;
        }

        $method = Str::upper((string) Arr::last($this->getNameParts()));
        if (!(
            $method === self::METHOD_HEAD
            || $method === self::METHOD_GET
            || $method === self::METHOD_POST
            || $method === self::METHOD_PUT
            || $method === self::METHOD_DELETE
            || $method === self::METHOD_PATCH
        )) {
            throw new LogicException(sprintf('Invalid method: %s', $method));
        }

        return $this->Method = $method;
    }
}
