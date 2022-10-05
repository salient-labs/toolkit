<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Convert;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Sync\Support\SyncEntityProvider;

/**
 * Resolves names to entities
 *
 */
final class SyncEntityResolver implements ISyncEntityResolver
{
    /**
     * @var SyncEntityProvider
     */
    private $EntityProvider;

    /**
     * @var string
     */
    private $NameField;

    public function __construct(SyncEntityProvider $entityProvider, string $nameField)
    {
        $this->EntityProvider = $entityProvider;
        $this->NameField      = $nameField;
    }

    public function getByName(string $name): ?SyncEntity
    {
        $match = Convert::iterableToItem(
            $this->EntityProvider->getList([$this->NameField => $name]),
            $this->NameField,
            $name
        );
        if ($match === false)
        {
            return null;
        }

        return $match;
    }
}
