<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * @lkrms-sample-entity https://jsonplaceholder.typicode.com/posts
 * @lkrms-generate-command lk-util generate sync entity --class='Lkrms\Tests\Sync\Entity\Post' --visibility='public' --provider='\Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint='/posts'
 */
class Post extends \Lkrms\Sync\SyncEntity
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
