<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\Concept\SyncEntity;

/**
 * @lkrms-sample-entity https://jsonplaceholder.typicode.com/users
 * @lkrms-generate-command lk-util generate sync entity --generate='Lkrms\Tests\Sync\Entity\User' --visibility='public' --provider='Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint='/users' --method='get'
 */
class User extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var string|null
     */
    public $Username;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var array|null
     */
    public $Address;

    /**
     * @var string|null
     */
    public $Phone;

    // Commented out for IExtensible testing

    ///**
    // * @var string|null
    // */
    //public $Website;

    /**
     * @var array|null
     */
    public $Company;

    /**
     * @var Post[]|null
     */
    public $Posts;

}
