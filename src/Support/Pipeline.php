<?php declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Concept\FluentInterface;
use Lkrms\Concern\TMutable;
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
 */
class Pipeline extends FluentInterface implements IPipeline
{
    use TMutable;

    /**
     * @var IContainer|null
     */
    private $Container;

    private $Payload;

    /**
     * @var int
     */
    private $PayloadConformity;

    /**
     * @var array|null
     */
    private $Args;

    /**
     * @var bool|null
     */
    private $Stream;

    /**
     * @var array<int,IPipe|Closure|string>
     */
    private $Pipes = [];

    /**
     * @var Closure|null
     */
    private $After;

    /**
     * @var array
     */
    private $AfterArgs = [];

    /**
     * @var Closure|null
     */
    private $Then;

    /**
     * @var array
     */
    private $ThenArgs = [];

    /**
     * @var Closure|null
     */
    private $Unless;

    /**
     * @var array
     */
    private $UnlessArgs = [];

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

    final public function send($payload, ...$args)
    {
        $_this                    = $this->getMutable();
        $_this->Payload           = $payload;
        $_this->PayloadConformity = ArrayKeyConformity::NONE;
        $_this->Args              = $args;
        $_this->Stream            = false;

        return $_this;
    }

    final public function stream(iterable $payload, ...$args)
    {
        $_this                    = $this->getMutable();
        $_this->Payload           = $payload;
        $_this->PayloadConformity = ArrayKeyConformity::NONE;
        $_this->Args              = $args;
        $_this->Stream            = true;

        return $_this;
    }

    final public function withConformity(int $conformity = ArrayKeyConformity::PARTIAL)
    {
        $_this                    = $this->getMutable();
        $_this->PayloadConformity = $conformity;

        return $_this;
    }

    final public function after(callable $callback, ...$args)
    {
        if ($this->After) {
            throw new RuntimeException(static::class . '::after() has already been applied');
        }
        $_this            = $this->getMutable();
        $_this->After     = $callback;
        $_this->AfterArgs = $args;

        return $_this;
    }

    final public function through(...$pipes)
    {
        $_this = $this->getMutable();
        array_push($_this->Pipes, ...$pipes);

        return $_this;
    }

    final public function throughCallback(callable $callback, bool $suppressArgs = false)
    {
        return $suppressArgs
            ? $this->through(fn($payload, Closure $next) => $next($callback($payload)))
            : $this->through(fn($payload, Closure $next, IPipeline $pipeline, ...$args) => $next($callback($payload, $pipeline, ...$args)));
    }

    final public function throughKeyMap(array $keyMap, int $flags = ArrayMapperFlag::ADD_UNMAPPED)
    {
        return $this->through(fn($payload, Closure $next, IPipeline $pipeline) => $next(
            (Mapper::getKeyMapClosure($keyMap, $pipeline->getConformity(), $flags))($payload)
        ));
    }

    final public function then(callable $callback, ...$args)
    {
        if ($this->Then) {
            throw new RuntimeException(static::class . '::then() has already been applied');
        }
        $_this           = $this->getMutable();
        $_this->Then     = $callback;
        $_this->ThenArgs = $args;

        return $_this;
    }

    final public function unless(callable $filter, ...$args)
    {
        if ($this->Unless) {
            throw new RuntimeException(static::class . '::unless() has already been applied');
        }
        $_this             = $this->getMutable();
        $_this->Unless     = $filter;
        $_this->UnlessArgs = $args;

        return $_this;
    }

    final public function run()
    {
        if ($this->Stream) {
            throw new RuntimeException(static::class . '::run() cannot be called after ' . static::class . '::stream()');
        }

        $result = ($this->getClosure())($this->After
            ? $this->After($this->Payload, $this, ...$this->AfterArgs, ...$this->Args)
            : $this->Payload);

        if ($this->Unless && ($this->Unless)($result, $this, ...$this->UnlessArgs, ...$this->Args) !== true) {
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
                ? $this->After($payload, $this, ...$this->AfterArgs, ...$this->Args)
                : $payload);

            if ($this->Unless && ($this->Unless)($result, $this, ...$this->UnlessArgs, ...$this->Args) !== true) {
                continue;
            }

            yield $result;
        }
    }

    final public function getConformity(): int
    {
        return $this->PayloadConformity;
    }

    /**
     * Run the pipeline
     *
     * If {@see Pipeline::stream()} has been called, use
     * {@see Pipeline::start()} to run the pipeline and return an iterator,
     * otherwise call {@see Pipeline::run()} and return the result.
     */
    final public function go()
    {
        if ($this->Stream) {
            return $this->start();
        }

        return $this->run();
    }

    protected function handleException($payload, Throwable $ex)
    {
        throw $ex;
    }

    private function getClosure(): Closure
    {
        return array_reduce(
            array_reverse($this->Pipes),
            function (Closure $next, $pipe): Closure {
                if (is_callable($pipe)) {
                    $closure = fn($payload) => $pipe($payload, $next, $this, ...$this->Args);
                } else {
                    if (is_string($pipe)) {
                        $container = $this->Container ?: Container::maybeGetGlobalContainer();
                        $pipe      = $container ? $container->get($pipe) : new $pipe();
                    }
                    if (!($pipe instanceof IPipe)) {
                        throw new UnexpectedValueException('Pipe does not implement ' . IPipe::class);
                    }
                    $closure = fn($payload) => $pipe->handle($payload, $next, $this, ...$this->Args);
                }

                return function ($payload) use ($closure) {
                    try {
                        return $closure($payload);
                    } catch (Throwable $ex) {
                        return $this->handleException($payload, $ex);
                    }
                };
            },
            fn($result) => ($this->Then ?: fn($result) => $result)($result, $this, ...$this->ThenArgs, ...$this->Args)
        );
    }

    final protected function toImmutable(): PipelineImmutable
    {
        $immutable = new PipelineImmutable();
        foreach ($this as $property => $value) {
            $immutable->$property = $value;
        }

        return $immutable;
    }
}
