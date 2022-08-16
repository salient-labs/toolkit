<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * @property int|string|null $Id
 * @property string|null $Name
 * @property string|null $Username
 * @property string|null $Email
 * @property array|null $Address
 * @property string|null $Phone
 * @property string|null $Website
 * @property array|null $Company
 * @property Post[]|null $Posts
 *
 * @lkrms-sample-entity https://jsonplaceholder.typicode.com/users
 * @lkrms-generate-command lk-util generate sync entity --class='Lkrms\Tests\Sync\Entity\User' --visibility='protected' --provider='\Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint='/users'
 */
class User extends \Lkrms\Sync\SyncEntity
{
    /**
     * @internal
     * @var int|string|null
     */
    protected $Id;

    /**
     * @internal
     * @var string|null
     */
    protected $Name;

    /**
     * @internal
     * @var string|null
     */
    protected $Username;

    /**
     * @internal
     * @var string|null
     */
    protected $Email;

    /**
     * @internal
     * @var array|null
     */
    protected $Address;

    /**
     * @internal
     * @var string|null
     */
    protected $Phone;

    // Commented out for IExtensible / __clone() testing

    ///**
    // * @internal
    // * @var string|null
    // */
    //protected $Website;

    /**
     * @internal
     * @var array|null
     */
    protected $Company;

    /**
     * @internal
     * @var Post[]|null
     */
    protected $Posts;

}
