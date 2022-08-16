<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * @property int|string|null $Id
 * @property User|null $User
 * @property string|null $Title
 * @property string|null $Body
 *
 * @lkrms-sample-entity https://jsonplaceholder.typicode.com/posts
 * @lkrms-generate-command lk-util generate sync entity --class='Lkrms\Tests\Sync\Entity\Post' --visibility='protected' --provider='\Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint='/posts'
 */
class Post extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var int|string|null
     */
    protected $Id;

    /**
     * @var User|null
     */
    protected $User;

    /**
     * @var string|null
     */
    protected $Title;

    /**
     * @var string|null
     */
    protected $Body;

}
