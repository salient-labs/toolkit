<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\SyncEntity;

class User extends SyncEntity
{
    /**
     * @var int
     */
    public $Id;

    /**
     * @var string
     */
    public $Name;

    /**
     * @var string
     */
    public $Username;

    /**
     * @var string
     */
    public $Email;

    /**
     * @var array
     */
    public $Address;

    /**
     * @var array
     */
    public $Company;
}

