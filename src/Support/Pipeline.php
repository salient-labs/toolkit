<?php declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipe;
use Lkrms\Contract\IPipeline;
use Lkrms\Exception\PipelineException;
use Lkrms\Facade\Mapper;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Sends a payload through a series of pipes to a destination
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 * @implements IPipeline<TInput,TOutput,TArgument>
 */
final class Pipeline extends FluentInterface implements IPipeline
{
    /**
     * @var IContainer|null
     */
    private $Container;

    /**
     * @var TInput|iterable<TInput>
     */
    private $Payload;

    /**
     * @var int
     * @phpstan-var ArrayKeyConformity::*
     */
    private $PayloadConformity;

    /**
     * @var TArgument|null
     */
    private $Arg;

    /**
     * @var bool|null
     */
    private $Stream;

    /**
     * @var array<IPipe|callable|class-string<IPipe>>
     * @phpstan-var array<IPipe<TInput,TOutput,TArgument>|(callable(TInput|TOutput, \Closure, IPipeline, TArgument): (TInput|TOutput))|class-string<IPipe>>
     */
    private $Pipes = [];

    /**
     * @var callable|null
     * @phpstan-var (callable(TInput, IPipeline, TArgument): (TInput|TOutput))|null
     */
    private $After;

    /**
     * @var callable|null
     * @phpstan-var (callable(TInput, IPipeline, TArgument): TOutput)|null
     */
    private $Then;

    /**
     * @var callable|null
     * @phpstan-var (callable(TOutput, IPipeline, TArgument): bool)|null
     */
    private $Unless;

    public function __construct(?IContainer $container = null)
    {
        $this->Container = $container;
    }

    public static function create(?IContainer $container = null): IPipeline
    {
        return new self($container);
    }

    public function send($payload, $arg = null)
    {
        $clone = clone $this;
        $clone->Payload = $payload;
        $clone->PayloadConformity = ArrayKeyConformity::NONE;
        $clone->Arg = $arg;
        $clone->Stream = false;

        return $clone;
    }

    public function stream(iterable $payload, $arg = null)
    {
        $clone = clone $this;
        $clone->Payload = $payload;
        $clone->PayloadConformity = ArrayKeyConformity::NONE;
        $clone->Arg = $arg;
        $clone->Stream = true;

        return $clone;
    }

    public function withConformity($conformity = ArrayKeyConformity::PARTIAL)
    {
        $clone = clone $this;
        $clone->PayloadConformity = $conformity;

        return $clone;
    }

    public function after(callable $callback)
    {
        if ($this->After) {
            throw new RuntimeException(static::class . '::after() has already been applied');
        }
        $clone = clone $this;
        $clone->After = $callback;

        return $clone;
    }

    public function afterIf(callable $callback)
    {
        if ($this->After) {
            return $this;
        }

        return $this->after($callback);
    }

    public function through(...$pipes)
    {
        $clone = clone $this;
        array_push($clone->Pipes, ...$pipes);

        return $clone;
    }

    public function throughCallback(callable $callback)
    {
        return $this->through(
            fn($payload, Closure $next, IPipeline $pipeline, $args) =>
                $next($callback($payload, $pipeline, $args))
        );
    }

    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        return $this->through(
            fn($payload, Closure $next, IPipeline $pipeline) =>
                $next((Mapper::getKeyMapClosure(
                    $keyMap,
                    $pipeline->getConformity(),
                    $flags
                ))($payload))
        );
    }

    public function then(callable $callback)
    {
        if ($this->Then) {
            throw new RuntimeException(static::class . '::then() has already been applied');
        }
        $clone = clone $this;
        $clone->Then = $callback;

        return $clone;
    }

    public function thenIf(callable $callback)
    {
        if ($this->Then) {
            return $this;
        }

        return $this->then($callback);
    }

    public function unless(callable $filter)
    {
        if ($this->Unless) {
            throw new RuntimeException(static::class . '::unless() has already been applied');
        }
        $clone = clone $this;
        $clone->Unless = $filter;

        return $clone;
    }

    public function unlessIf(callable $filter)
    {
        if ($this->Unless) {
            return $this;
        }

        return $this->unless($filter);
    }

    public function run()
    {
        if ($this->Stream) {
            throw new RuntimeException(static::class . '::run() cannot be called after ' . static::class . '::stream()');
        }

        $result = $this->getClosure()(
            $this->After
                ? ($this->After)($this->Payload, $this, $this->Arg)
                : $this->Payload
        );

        if ($this->Unless &&
                ($this->Unless)($result, $this, $this->Arg) === true) {
            throw new PipelineException('Result rejected by filter');
        }

        return $result;
    }

    public function start(): iterable
    {
        if (!$this->Stream) {
            throw new RuntimeException(static::class . '::stream() must be called before ' . static::class . '::start()');
        }

        $closure = $this->getClosure();
        foreach ($this->Payload as $key => $payload) {
            $result = $closure(
                $this->After
                    ? ($this->After)($payload, $this, $this->Arg)
                    : $payload
            );

            if ($this->Unless &&
                    ($this->Unless)($result, $this, $this->Arg) === true) {
                continue;
            }

            yield $key => $result;
        }
    }

    public function getConformity(): int
    {
        return $this->PayloadConformity;
    }

    public function runThrough(IPipeline $next)
    {
        return $next->send($this->run(), $this->Arg);
    }

    public function startThrough(IPipeline $next)
    {
        return $next->stream($this->start(), $this->Arg);
    }

    private function handleException($payload, Throwable $ex)
    {
        throw $ex;
    }

    private function getClosure(): Closure
    {
        return array_reduce(
            array_reverse($this->Pipes),
            function (Closure $next, $pipe): Closure {
                if (is_callable($pipe)) {
                    $closure = fn($payload) => $pipe($payload, $next, $this, $this->Arg);
                } else {
                    if (is_string($pipe)) {
                        $container = $this->Container ?: Container::maybeGetGlobalContainer();
                        $pipe = $container ? $container->get($pipe) : new $pipe();
                    }
                    if (!($pipe instanceof IPipe)) {
                        throw new UnexpectedValueException('Pipe does not implement ' . IPipe::class);
                    }
                    $closure = fn($payload) => $pipe->handle($payload, $next, $this, $this->Arg);
                }

                return
                    function ($payload) use ($closure) {
                        try {
                            return $closure($payload);
                        } catch (Throwable $ex) {
                            return $this->handleException($payload, $ex);
                        }
                    };
            },
            $this->Then
                ? fn($result) => ($this->Then)($result, $this, $this->Arg)
                : fn($result) => $result
        );
    }
}
