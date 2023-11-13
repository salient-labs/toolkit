<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\HasName;

/**
 * An event dispatched by a service
 *
 * @template TService of object
 */
class ServiceEvent implements HasName
{
    protected string $Name;

    /**
     * @var TService|null
     */
    protected ?object $Service;

    /**
     * Creates a new ServiceEvent object
     *
     * @param TService $service
     */
    public function __construct(string $name, ?object $service)
    {
        $this->Name = $name;
        $this->Service = $service;
    }

    /**
     * Get the name of the event
     */
    public function name(): string
    {
        return $this->Name;
    }

    /**
     * Get the service that dispatched the event
     *
     * @return TService|null
     */
    public function service(): ?object
    {
        return $this->Service;
    }
}
