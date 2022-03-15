<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\SyncEntity;

class Post extends SyncEntity
{
    /**
     * @var int
     */
    public $Id;

    /**
     * @var string
     */
    public $Title;

    /**
     * @var string
     */
    public $Body;

    /**
     * @var User
     */
    public $User;
}

