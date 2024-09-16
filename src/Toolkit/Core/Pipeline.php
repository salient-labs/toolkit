<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Pipeline\EntityPipelineInterface;
use Salient\Contract\Core\Pipeline\PipeInterface;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Core\Pipeline\StreamPipelineInterface;
use Salient\Contract\Core\ArrayMapperFlag;
use Salient\Contract\Core\ListConformity;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Concern\HasMutator;
use Salient\Core\Facade\App;
use Salient\Utility\Arr;
use Closure;
use Generator;
use LogicException;

/**
 * Sends a payload through a series of pipes
 *
 * @template TInput
 * @template TOutput
 * @template TArgument
 *
 * @implements PipelineInterface<TInput,TOutput,TArgument>
 * @implements EntityPipelineInterface<TInput,TOutput,TArgument>
 * @implements StreamPipelineInterface<TInput,TOutput,TArgument>
 */
final class Pipeline implements
    PipelineInterface,
    EntityPipelineInterface,
    StreamPipelineInterface
{
    use HasChainableMethods;
    use HasMutator;

    private bool $HasPayload = false;
    private bool $HasStream;
    /** @var iterable<TInput>|TInput */
    private $Payload;
    /** @var TArgument */
    private $Arg;
    /** @var ListConformity::* */
    private int $PayloadConformity = ListConformity::NONE;
    /** @var (Closure(TInput $payload, static $pipeline, TArgument $arg): (TInput|TOutput))|null */
    private ?Closure $After = null;
    /** @var array<(Closure(TInput $payload, Closure $next, static $pipeline, TArgument $arg): (TInput|TOutput))|(Closure(TOutput $payload, Closure $next, static $pipeline, TArgument $arg): TOutput)|PipeInterface<TInput,TOutput,TArgument>|class-string<PipeInterface<TInput,TOutput,TArgument>>> */
    private array $Pipes = [];
    /** @var array<array{array<array-key,array-key|array-key[]>,int-mask-of<ArrayMapperFlag::*>}> */
    private array $KeyMaps = [];
    /** @var ArrayMapper[] */
    private array $ArrayMappers;
    /** @var (Closure(TInput $result, static $pipeline, TArgument $arg): TOutput)|(Closure(TOutput $result, static $pipeline, TArgument $arg): TOutput)|null */
    private ?Closure $Then = null;
    /** @var (Closure(array<TInput> $results, static $pipeline, TArgument $arg): iterable<TOutput>)|(Closure(array<TOutput> $results, static $pipeline, TArgument $arg): iterable<TOutput>)|null */
    private ?Closure $CollectThen = null;
    /** @var array<Closure(TOutput $result, static $pipeline, TArgument $arg): mixed> */
    private array $Cc = [];
    /** @var (Closure(TOutput, static, TArgument): bool)|null */
    private ?Closure $Unless = null;
    private ?ContainerInterface $Container;

    /**
     * Creates a new Pipeline object
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->Container = $container;
    }

    /**
     * Creates a new Pipeline object
     *
     * @return PipelineInterface<mixed,mixed,mixed>
     */
    public static function create(?ContainerInterface $container = null): PipelineInterface
    {
        return new self($container);
    }

    /**
     * @internal
     */
    public function __clone()
    {
        unset($this->ArrayMappers);
    }

    /**
     * @inheritDoc
     */
    public function send($payload, $arg = null)
    {
        return $this->withPayload($payload, $arg, false);
    }

    /**
     * @inheritDoc
     */
    public function stream(iterable $payload, $arg = null)
    {
        return $this->withPayload($payload, $arg, true);
    }

    /**
     * @template T0
     * @template T1
     *
     * @param iterable<T0>|T0 $payload
     * @param T1 $arg
     * @return static<TInput&T0,TOutput,TArgument&T1>
     */
    private function withPayload($payload, $arg, bool $stream)
    {
        if ($this->HasPayload) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Payload already set');
            // @codeCoverageIgnoreEnd
        }

        /** @var static<TInput&T0,TOutput,TArgument&T1> */
        return $this
            ->with('HasPayload', true)
            ->with('HasStream', $stream)
            ->with('Payload', $payload)
            ->with('Arg', $arg);
    }

    /**
     * @inheritDoc
     */
    public function withConformity($conformity)
    {
        $this->assertHasPayload();

        return $this->with('PayloadConformity', $conformity);
    }

    /**
     * @inheritDoc
     */
    public function getConformity()
    {
        $this->assertHasPayload();

        return $this->PayloadConformity;
    }

    /**
     * @inheritDoc
     */
    public function after(Closure $closure)
    {
        if ($this->After) {
            // @codeCoverageIgnoreStart
            throw new LogicException(static::class . '::after() already applied');
            // @codeCoverageIgnoreEnd
        }

        return $this->with('After', $closure);
    }

    /**
     * @inheritDoc
     */
    public function afterIf(Closure $closure)
    {
        return $this->After
            ? $this
            : $this->after($closure);
    }

    /**
     * @inheritDoc
     */
    public function through($pipe)
    {
        return $this->with('Pipes', Arr::push($this->Pipes, $pipe));
    }

    /**
     * @inheritDoc
     */
    public function throughClosure(Closure $closure)
    {
        return $this->through(
            fn($payload, Closure $next, self $pipeline, $arg) =>
                $next($closure($payload, $pipeline, $arg))
        );
    }

    /**
     * @inheritDoc
     */
    public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        $keyMapKey = count($this->KeyMaps);
        $clone = $this->through(
            function ($payload, Closure $next, self $pipeline) use ($keyMapKey) {
                /** @var mixed[] $payload */
                return $next($pipeline->ArrayMappers[$keyMapKey]->map($payload));
            }
        );
        $clone->KeyMaps[] = [$keyMap, $flags];
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function then(Closure $closure)
    {
        if ($this->Then || $this->CollectThen) {
            throw new LogicException(static::class . '::then() already applied');
        }

        return $this->with('Then', $closure);
    }

    /**
     * @inheritDoc
     */
    public function thenIf(Closure $closure)
    {
        return $this->Then || $this->CollectThen
            ? $this
            : $this->then($closure);
    }

    /**
     * @inheritDoc
     */
    public function collectThen(Closure $closure)
    {
        $this->assertHasStream();

        if ($this->Then || $this->CollectThen) {
            throw new LogicException(static::class . '::then() already applied');
        }

        return $this->with('CollectThen', $closure);
    }

    /**
     * @inheritDoc
     */
    public function collectThenIf(Closure $closure)
    {
        $this->assertHasStream();

        return $this->Then || $this->CollectThen
            ? $this
            : $this->collectThen($closure);
    }

    /**
     * @inheritDoc
     */
    public function cc(Closure $closure)
    {
        return $this->with('Cc', Arr::push($this->Cc, $closure));
    }

    /**
     * @inheritDoc
     */
    public function unless(Closure $filter)
    {
        $this->assertHasStream();

        if ($this->Unless) {
            throw new LogicException(static::class . '::unless() already applied');
        }

        return $this->with('Unless', $filter);
    }

    /**
     * @inheritDoc
     */
    public function unlessIf(Closure $filter)
    {
        $this->assertHasStream();

        return $this->Unless
            ? $this
            : $this->unless($filter);
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->assertHasOnePayload();

        $result = $this->getClosure()(
            $this->After
                ? ($this->After)($this->Payload, $this, $this->Arg)
                : $this->Payload
        );

        if ($this->Cc) {
            $this->ccResult($result);
        }

        return $result;
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
    public function start(): Generator
    {
        $this->assertHasStream();

        $closure = $this->getClosure();
        $results = [];
        foreach ($this->Payload as $key => $payload) {
            $result = $closure(
                $this->After
                    ? ($this->After)($payload, $this, $this->Arg)
                    : $payload
            );

            if (
                $this->Unless
                && ($this->Unless)($result, $this, $this->Arg) !== false
            ) {
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

        $results = ($this->CollectThen)($results, $this, $this->Arg);
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
    public function startInto(PipelineInterface $next)
    {
        return $next->stream($this->start(), $this->Arg);
    }

    /**
     * @param mixed $result
     */
    private function ccResult($result): void
    {
        foreach ($this->Cc as $closure) {
            $closure($result, $this, $this->Arg);
        }
    }

    private function getClosure(): Closure
    {
        $this->ArrayMappers = [];
        foreach ($this->KeyMaps as [$keyMap, $flags]) {
            $this->ArrayMappers[] = new ArrayMapper($keyMap, $this->PayloadConformity, $flags);
        }

        $closure = $this->Then
            ? fn($result) => ($this->Then)($result, $this, $this->Arg)
            : fn($result) => $result;

        foreach (array_reverse($this->Pipes) as $pipe) {
            if (is_string($pipe)) {
                $pipe = $this->Container
                    ? $this->Container->get($pipe)
                    : App::get($pipe);

                if (!$pipe instanceof PipeInterface) {
                    throw new LogicException(sprintf(
                        '%s does not implement %s',
                        get_class($pipe),
                        PipeInterface::class,
                    ));
                }
            }

            $closure = fn($payload) =>
                $pipe($payload, $closure, $this, $this->Arg);
        }

        return $closure;
    }

    /**
     * @phpstan-assert iterable<TInput> $this->Payload
     */
    private function assertHasStream(): void
    {
        $this->assertHasPayload(true);
    }

    /**
     * @phpstan-assert TInput $this->Payload
     */
    private function assertHasOnePayload(): void
    {
        $this->assertHasPayload(false);
    }

    private function assertHasPayload(?bool $stream = null): void
    {
        if (!$this->HasPayload) {
            throw new LogicException('No payload');
        }
        if ($stream !== null && $this->HasStream !== $stream) {
            throw new LogicException('Invalid payload');
        }
    }
}
