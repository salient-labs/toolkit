<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\Concept\SyncEntity;

/**
 * @lkrms-sample-entity https://jsonplaceholder.typicode.com/posts
 * @lkrms-generate-command lk-util generate sync entity --generate='Lkrms\Tests\Sync\Entity\Post' --visibility='public' --provider='Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint='/posts'
 */
class Post extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var User|null
     */
    public $User;

    /**
     * @var string|null
     */
    public $Title;

    /**
     * @var string|null
     */
    public $Body;

}
