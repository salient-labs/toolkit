<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 *
 * @package Lkrms\Tests
 */
class Post extends \Lkrms\Sync\SyncEntity
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
