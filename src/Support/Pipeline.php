<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Concern\HasContainer;
use Lkrms\Container\Container;
use Lkrms\Contract\IPipe;
use Lkrms\Contract\IPipeline;
use Lkrms\Facade\Mapper;
use UnexpectedValueException;

class Pipeline implements IPipeline
{
    use HasContainer;

    private $Payload;

    /**
     * @var array<int,IPipe|Closure|string>
     */
    private $Pipes = [];

    /**
     * @var Closure|null
     */
    private $PipeStack;

    /**
     * @var Closure
     */
    private $Destination;

    /**
     * @return static
     */
    final public static function create(?Container $container = null)
    {
        return ($container ?: (Container::hasGlobalContainer()
            ? Container::getGlobalContainer()
            : new Container()))->get(static::class);
    }

    public function send($payload)
    {
        $this->Payload = $payload;

        return $this;
    }

    public function through(...$pipes)
    {
        $this->Pipes     = $pipes;
        $this->PipeStack = null;

        return $this;
    }

    public function pipe($pipe)
    {
        $this->Pipes[]   = $pipe;
        $this->PipeStack = null;

        return $this;
    }

    public function apply(callable $callback)
    {
        $this->Pipes[]   = fn($payload, Closure $next) => $next($callback($payload));
        $this->PipeStack = null;

        return $this;
    }

    public function map(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = 0)
    {
        $this->Pipes[] = fn($payload, Closure $next) => $next(
            (Mapper::getKeyMapClosure($keyMap, $conformity, $flags))($payload)
        );
        $this->PipeStack = null;

        return $this;
    }

    public function then(callable $callback)
    {
        $this->Destination = $callback;

        return ($this->getPipeStack())($this->Payload);
    }

    public function thenReturn()
    {
        return $this->then(fn($result) => $result);
    }

    public function thenStream(): iterable
    {
        $this->Destination = fn($result) => $result;

        $pipeStack = $this->getPipeStack();
        foreach ($this->Payload as $payload)
        {
            yield ($pipeStack)($payload);
        }
    }

    private function getPipeStack(): Closure
    {
        return $this->PipeStack ?: ($this->PipeStack = array_reduce(
            array_reverse($this->Pipes),
            function (Closure $next, $pipe): Closure
            {
                if (is_callable($pipe))
                {
                    return fn($payload) => $pipe($payload, $next);
                }
                elseif (is_string($pipe))
                {
                    if (!is_a($pipe, IPipe::class, true))
                    {
                        throw new UnexpectedValueException($pipe . " does not implement " . IPipe::class);
                    }
                    $pipe = $this->container()->get($pipe);
                }
                elseif (!($pipe instanceof IPipe))
                {
                    throw new UnexpectedValueException("Invalid pipe");
                }
                /** @var IPipe $pipe */
                return fn($payload) => $pipe->handle($payload, $next);
            },
            fn($result) => ($this->Destination)($result)
        ));
    }

}
