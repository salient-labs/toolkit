<?php declare(strict_types=1);

namespace Salient\Core;

use Lkrms\Container\ContainerInterface;
use Lkrms\Facade\App;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Support\ArrayMapper;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Contract\EntityPipelineInterface;
use Salient\Core\Contract\PipeInterface;
use Salient\Core\Contract\PipelineInterface;
use Salient\Core\Contract\StreamPipelineInterface;
use Salient\Core\Utility\Get;
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

    private bool $HasPayload = false;

    private bool $HasStream = false;

    /**
     * @var iterable<TInput>|TInput
     */
    private $Payload;

    /**
     * @var TArgument
     */
    private $Arg;

    /**
     * @var ArrayKeyConformity::*
     */
    private int $PayloadConformity = ArrayKeyConformity::NONE;

    /**
     * @var (Closure(TInput $payload, static $pipeline, TArgument $arg): (TInput|TOutput))|null
     */
    private ?Closure $After = null;

    /**
     * @var array<(Closure(TInput|TOutput $payload, Closure $next, static $pipeline, TArgument $arg): (TInput|TOutput))|PipeInterface<TInput,TOutput,TArgument>|class-string<PipeInterface<TInput,TOutput,TArgument>>>
     */
    private array $Pipes = [];

    /**
     * @var array<array{array<array-key,array-key|array-key[]>,int-mask-of<ArrayMapperFlag::*>}>
     */
    private array $KeyMaps = [];

    /**
     * @var ArrayMapper[]
     */
    private array $ArrayMappers;

    /**
     * @var (Closure(TInput|TOutput $result, static $pipeline, TArgument $arg): TOutput)|null
     */
    private ?Closure $Then = null;

    /**
     * @var (Closure(array<TInput|TOutput> $results, static $pipeline, TArgument $arg): iterable<TOutput>)|null
     */
    private ?Closure $CollectThen = null;

    /**
     * @var array<Closure(TOutput $result, static $pipeline, TArgument $arg): mixed>
     */
    private array $Cc = [];

    /**
     * @var (Closure(TOutput, static, TArgument): bool)|null
     */
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
     * Syntactic sugar for `new Pipeline()`.
     *
     * @return static
     */
    public static function create(?ContainerInterface $container = null): self
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
        if ($this->HasPayload) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Payload already set');
            // @codeCoverageIgnoreEnd
        }

        $clone = clone $this;
        $clone->HasPayload = true;
        $clone->HasStream = false;
        $clone->Payload = $payload;
        $clone->Arg = $arg;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function stream(iterable $payload, $arg = null)
    {
        if ($this->HasPayload) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Payload already set');
            // @codeCoverageIgnoreEnd
        }

        $clone = clone $this;
        $clone->HasPayload = true;
        $clone->HasStream = true;
        $clone->Payload = $payload;
        $clone->Arg = $arg;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withConformity($conformity)
    {
        if (!$this->HasPayload) {
            // @codeCoverageIgnoreStart
            throw new LogicException('No payload');
            // @codeCoverageIgnoreEnd
        }

        $clone = clone $this;
        $clone->PayloadConformity = $conformity;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getConformity()
    {
        if (!$this->HasPayload) {
            // @codeCoverageIgnoreStart
            throw new LogicException('No payload');
            // @codeCoverageIgnoreEnd
        }

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

        $clone = clone $this;
        $clone->After = $closure;
        return $clone;
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
    public function through(...$pipes)
    {
        $clone = clone $this;
        array_push($clone->Pipes, ...$pipes);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function throughClosure(Closure $closure)
    {
        return $this->through(
            fn($payload, Closure $next, self $pipeline, $args) =>
                $next($closure($payload, $pipeline, $args))
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
                $next($pipeline->ArrayMappers[$keyMapKey]->map($payload))
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

        $clone = clone $this;
        $clone->Then = $closure;
        return $clone;
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
        if (!$this->HasPayload || !$this->HasStream) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Invalid payload');
            // @codeCoverageIgnoreEnd
        }

        if ($this->Then || $this->CollectThen) {
            throw new LogicException(static::class . '::then() already applied');
        }

        $clone = clone $this;
        $clone->CollectThen = $closure;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function collectThenIf(Closure $closure)
    {
        if (!$this->HasPayload || !$this->HasStream) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Invalid payload');
            // @codeCoverageIgnoreEnd
        }

        return $this->Then || $this->CollectThen
            ? $this
            : $this->collectThen($closure);
    }

    /**
     * @inheritDoc
     */
    public function cc(Closure $closure)
    {
        $clone = clone $this;
        $clone->Cc[] = $closure;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unless(Closure $filter)
    {
        if (!$this->HasPayload || !$this->HasStream) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Invalid payload');
            // @codeCoverageIgnoreEnd
        }

        if ($this->Unless) {
            throw new LogicException(static::class . '::unless() already applied');
        }

        $clone = clone $this;
        $clone->Unless = $filter;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unlessIf(Closure $filter)
    {
        if (!$this->HasPayload || !$this->HasStream) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Invalid payload');
            // @codeCoverageIgnoreEnd
        }

        return $this->Unless
            ? $this
            : $this->unless($filter);
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        if (!$this->HasPayload || $this->HasStream) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Invalid payload');
            // @codeCoverageIgnoreEnd
        }

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
        if (!$this->HasPayload || !$this->HasStream) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Invalid payload');
            // @codeCoverageIgnoreEnd
        }

        $closure = $this->getClosure();
        $results = [];
        foreach ($this->Payload as $key => $payload) {
            $result = $closure(
                $this->After
                    ? ($this->After)($payload, $this, $this->Arg)
                    : $payload
            );

            if (
                $this->Unless &&
                ($this->Unless)($result, $this, $this->Arg) !== false
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
            if ($pipe instanceof Closure) {
                $closure = fn($payload) => $pipe($payload, $closure, $this, $this->Arg);
                continue;
            }

            if (is_string($pipe)) {
                $pipe = $this->Container
                    ? $this->Container->get($pipe)
                    : App::get($pipe);
            }

            if (!($pipe instanceof PipeInterface)) {
                throw new LogicException(sprintf(
                    '%s does not implement %s',
                    Get::type($pipe),
                    PipeInterface::class,
                ));
            }

            $closure = fn($payload) => $pipe->handle($payload, $closure, $this, $this->Arg);
        }

        return $closure;
    }
}
