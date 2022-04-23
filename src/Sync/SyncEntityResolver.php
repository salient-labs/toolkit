<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Lkrms\Sync\Provider\SyncEntityProvider;
use UnexpectedValueException;

/**
 * Resolves names to entities
 *
 * @package Lkrms
 */
class SyncEntityResolver
{
    /**
     * @var SyncEntityProvider
     */
    protected $EntityProvider;

    /**
     * @var string
     */
    protected $NameField;

    /**
     *
     * @param SyncEntityProvider $entityProvider
     * @param string $nameField
     */
    public function __construct(
        SyncEntityProvider $entityProvider,
        string $nameField
    ) {
        $this->EntityProvider = $entityProvider;
        $this->NameField      = $nameField;
    }

    public function getByName(string $name): ?SyncEntity
    {
        $nameField = $this->NameField;
        $matches   = array_filter(
            $this->EntityProvider->getList([$nameField => $name]),
            function ($entity) use ($nameField, $name) { return ($entity->$nameField ?? null) == $name; }
        );

        if (count($matches) === 1)
        {
            return reset($matches);
        }
        elseif (empty($matches))
        {
            return null;
        }
        else
        {
            throw new UnexpectedValueException("More than one entity matched the criteria");
        }
    }
}
