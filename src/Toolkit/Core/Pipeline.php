<?php declare(strict_types=1);

namespace Salient\Core;

use Lkrms\Container\Container;
use Lkrms\Container\ContainerInterface;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Support\ArrayMapper;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Contract\PipeInterface;
use Salient\Core\Contract\PipelineInterface;
use Salient\Core\Exception\PipelineFilterException;
use Closure;
use Generator;
use LogicException;
use Throwable;

/**
 * Sends a payload through a series of pipes to a destination
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @implements PipelineInterface<TInput,TOutput,TArgument>
 */
final class Pipeline implements PipelineInterface
{
    use HasChainableMethods;

    /**
     * @var ContainerInterface|null
     */
    private $Container;

    /**
     * @var TInput|iterable<TInput>
     */
    private $Payload;

    /**
     * @var ArrayKeyConformity::*
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
     * @var array<PipeInterface<TInput,TOutput,TArgument>|(callable(TInput|TOutput, Closure, static, TArgument): (TInput|TOutput))|class-string<PipeInterface<TInput,TOutput,TArgument>>>
     */
    private $Pipes = [];

    /**
     * @var array<array{array<array-key,array-key|array-key[]>,int-mask-of<ArrayMapperFlag::*>}>
     */
    private $KeyMaps = [];

    /**
     * @var (callable(TInput, static, TArgument): (TInput|TOutput))|null
     */
    private $After;

    /**
     * @var (callable(TInput, static, TArgument): TOutput)|null
     */
    private $Then;

    /**
     * @var bool
     */
    private $CollectThen = false;

    /**
     * @var array<callable(TOutput, static, TArgument): mixed>
     */
    private $Cc = [];

    /**
     * @var (callable(TOutput, static, TArgument): bool)|null
     */
    private $Unless;

    /**
     * @var ArrayMapper[]
     */
    private $ArrayMappers = [];

    /**
     * Creates a new Pipeline object
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->Container = $container;
    }

    /**
     * Get a new pipeline
     *
     * Syntactic sugar for `new Pipeline()`.
     *
     * @return static
     */
    public static function create(?ContainerInterface $container = null): self
    {
        return new self($container);
    }

    /**
     * @inheritDoc
     */
    public function send($payload, $arg = null)
    {
        $clone = clone $this;
        $clone->Payload = $payload;
        $clone->PayloadConformity = ArrayKeyConformity::NONE;
        $clone->Arg = $arg;
        $clone->Stream = false;
        $clone->ArrayMappers = [];

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function stream(iterable $payload, $arg = null)
    {
        $clone = clone $this;
        $clone->Payload = $payload;
        $clone->PayloadConformity = ArrayKeyConformity::NONE;
        $clone->Arg = $arg;
        $clone->Stream = true;
        $clone->ArrayMappers = [];

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withConformity($conformity = ArrayKeyConformity::PARTIAL)
    {
        $clone = clone $this;
        $clone->PayloadConformity = $conformity;
        $clone->ArrayMappers = [];

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function after(callable $callback)
    {
        if ($this->After) {
            throw new LogicException(static::class . '::after() has already been applied');
        }
        $clone = clone $this;
        $clone->After = $callback;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function afterIf(callable $callback)
    {
        if ($this->After) {
            return $this;
        }

        return $this->after($callback);
    }

    /**
     * @inheritDoc
     */
    public function through(...$pipes)
    {
        $clone = clone $this;
        array_push($clone->Pipes, ...$pipes);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function throughCallback(callable $callback)
    {
        return $this->through(
            fn($payload, Closure $next, self $pipeline, $args) =>
                $next($callback($payload, $pipeline, $args))
        );
    }

    /**
     * @inheritDoc
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        $keyMapKey = count($this->KeyMaps);
        $clone = $this->through(
            fn($payload, Closure $next, self $pipeline) =>
                $next($pipeline->getArrayMapper($keyMapKey)->map($payload))
        );
        $clone->KeyMaps[] = [$keyMap, $flags];
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function then(callable $callback)
    {
        if ($this->Then) {
            throw new LogicException(static::class . '::then() has already been applied');
        }
        $clone = clone $this;
        $clone->Then = $callback;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function thenIf(callable $callback)
    {
        if ($this->Then) {
            return $this;
        }

        return $this->then($callback);
    }

    /**
     * @inheritDoc
     */
    public function collectThen(callable $callback)
    {
        if ($this->Then) {
            throw new LogicException(static::class . '::then() has already been applied');
        }
        $clone = clone $this;
        $clone->Then = $callback;
        $clone->CollectThen = true;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function collectThenIf(callable $callback)
    {
        if ($this->Then) {
            return $this;
        }

        return $this->collectThen($callback);
    }

    /**
     * @inheritDoc
     */
    public function cc(callable $callback)
    {
        $clone = clone $this;
        $clone->Cc[] = $callback;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unless(callable $filter)
    {
        if ($this->Unless) {
            throw new LogicException(static::class . '::unless() has already been applied');
        }
        $clone = clone $this;
        $clone->Unless = $filter;

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unlessIf(callable $filter)
    {
        if ($this->Unless) {
            return $this;
        }

        return $this->unless($filter);
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        if ($this->Stream || $this->CollectThen) {
            throw new LogicException(
                static::class . '::run() cannot be called after '
                . static::class . '::stream() or '
                . static::class . '::collectThen()'
            );
        }

        $result = $this->getClosure()(
            $this->After
                ? ($this->After)($this->Payload, $this, $this->Arg)
                : $this->Payload
        );

        if ($this->Unless &&
                ($this->Unless)($result, $this, $this->Arg) === true) {
            throw new PipelineFilterException($this->Payload, $result);
        }

        if ($this->Cc) {
            $this->ccResult($result);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function start(): Generator
    {
        if (!$this->Stream) {
            throw new LogicException(
                static::class . '::stream() must be called before '
                . static::class . '::start()'
            );
        }

        $closure = $this->getClosure();
        $results = [];
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

            if ($this->CollectThen) {
                $results[$key] = $result;
                continue;
            }

            if ($this->Cc) {
                $this->ccResult($result);
            }

            yield $key => $result;
        }

        if (!$this->CollectThen || !$results) {
            return;
        }

        $results = ($this->Then)($results, $this, $this->Arg);
        foreach ($results as $key => $result) {
            if ($this->Cc) {
                $this->ccResult($result);
            }

            yield $key => $result;
        }
    }

    /**
     * @inheritDoc
     */
    public function getConformity()
    {
        return $this->PayloadConformity;
    }

    /**
     * @inheritDoc
     */
    public function runInto(PipelineInterface $next)
    {
        return $next->send($this->run(), $this->Arg);
    }

    /**
     * @inheritDoc
     */
    public function startInto(PipelineInterface $next)
    {
        return $next->stream($this->start(), $this->Arg);
    }

    private function getArrayMapper(int $keyMapKey): ArrayMapper
    {
        return $this->ArrayMappers[$keyMapKey]
            ??= new ArrayMapper(
                $this->KeyMaps[$keyMapKey][0],
                $this->PayloadConformity,
                $this->KeyMaps[$keyMapKey][1],
            );
    }

    /**
     * @param mixed $result
     */
    private function ccResult($result): void
    {
        foreach ($this->Cc as $callback) {
            $callback($result, $this, $this->Arg);
        }
    }

    /**
     * @param mixed $payload
     * @return never
     */
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
                    if (!($pipe instanceof PipeInterface)) {
                        throw new LogicException('Pipe does not implement ' . PipeInterface::class);
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
            $this->Then && !$this->CollectThen
                ? fn($result) => ($this->Then)($result, $this, $this->Arg)
                : fn($result) => $result
        );
    }
}
