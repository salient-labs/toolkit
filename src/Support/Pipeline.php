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
     * @psalm-var ArrayKeyConformity::*
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
     * @var array<IPipe<TInput,TOutput,TArgument>|callable|class-string<IPipe>>
     * @psalm-var array<IPipe<TInput,TOutput,TArgument>|(callable(TInput|TOutput, \Closure, IPipeline, TArgument): (TInput|TOutput))|class-string<IPipe>>
     */
    private $Pipes = [];

    /**
     * @var callable|null
     * @psalm-var (callable(TInput, IPipeline, TArgument): (TInput|TOutput))|null
     */
    private $After;

    /**
     * @var callable|null
     * @psalm-var (callable(TInput, IPipeline, TArgument): TOutput)|null
     */
    private $Then;

    /**
     * @var callable|null
     * @psalm-var (callable(TOutput, IPipeline, TArgument): bool)|null
     */
    private $Unless;

    final public function __construct(?IContainer $container = null)
    {
        $this->Container = $container;
    }

    /**
     * @return static
     */
    final public static function create(?IContainer $container = null)
    {
        return new static($container);
    }

    final public function send($payload, $arg = null)
    {
        $_this                    = clone $this;
        $_this->Payload           = $payload;
        $_this->PayloadConformity = ArrayKeyConformity::NONE;
        $_this->Arg               = $arg;
        $_this->Stream            = false;

        return $_this;
    }

    final public function stream(iterable $payload, $arg = null)
    {
        $_this                    = clone $this;
        $_this->Payload           = $payload;
        $_this->PayloadConformity = ArrayKeyConformity::NONE;
        $_this->Arg               = $arg;
        $_this->Stream            = true;

        return $_this;
    }

    final public function withConformity(int $conformity = ArrayKeyConformity::PARTIAL)
    {
        $_this                    = clone $this;
        $_this->PayloadConformity = $conformity;

        return $_this;
    }

    final public function after(callable $callback)
    {
        if ($this->After) {
            throw new RuntimeException(static::class . '::after() has already been applied');
        }
        $_this        = clone $this;
        $_this->After = $callback;

        return $_this;
    }

    final public function through(...$pipes)
    {
        $_this = clone $this;
        array_push($_this->Pipes, ...$pipes);

        return $_this;
    }

    final public function throughCallback(callable $callback)
    {
        return $this->through(
            fn($payload, Closure $next, IPipeline $pipeline, $args) =>
                $next($callback($payload, $pipeline, $args))
        );
    }

    final public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
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

    final public function then(callable $callback)
    {
        if ($this->Then) {
            throw new RuntimeException(static::class . '::then() has already been applied');
        }
        $_this       = clone $this;
        $_this->Then = $callback;

        return $_this;
    }

    final public function unless(callable $filter)
    {
        if ($this->Unless) {
            throw new RuntimeException(static::class . '::unless() has already been applied');
        }
        $_this         = clone $this;
        $_this->Unless = $filter;

        return $_this;
    }

    final public function run()
    {
        if ($this->Stream) {
            throw new RuntimeException(static::class . '::run() cannot be called after ' . static::class . '::stream()');
        }

        $result = ($this->getClosure())($this->After
            ? ($this->After)($this->Payload, $this, $this->Arg)
            : $this->Payload);

        if ($this->Unless && ($this->Unless)($result, $this, $this->Arg) !== true) {
            throw new PipelineException('Result rejected by filter');
        }

        return $result;
    }

    final public function start(): iterable
    {
        if (!$this->Stream) {
            throw new RuntimeException(static::class . '::stream() must be called before ' . static::class . '::start()');
        }

        $closure = $this->getClosure();
        foreach ($this->Payload as $payload) {
            $result = ($closure)($this->After
                ? ($this->After)($payload, $this, $this->Arg)
                : $payload);

            if ($this->Unless && ($this->Unless)($result, $this, $this->Arg) !== true) {
                continue;
            }

            yield $result;
        }
    }

    final public function getConformity(): int
    {
        return $this->PayloadConformity;
    }

    final public function runThrough(IPipeline $next)
    {
        return $next->send($this->run(), $this->Arg);
    }

    final public function startThrough(IPipeline $next)
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
                        $pipe      = $container ? $container->get($pipe) : new $pipe();
                    }
                    if (!($pipe instanceof IPipe)) {
                        throw new UnexpectedValueException('Pipe does not implement ' . IPipe::class);
                    }
                    $closure = fn($payload) => $pipe->handle($payload, $next, $this, $this->Arg);
                }

                return function ($payload) use ($closure) {
                    try {
                        return $closure($payload);
                    } catch (Throwable $ex) {
                        return $this->handleException($payload, $ex);
                    }
                };
            },
            fn($result) => ($this->Then ?: fn($result) => $result)($result, $this, $this->Arg)
        );
    }
}
