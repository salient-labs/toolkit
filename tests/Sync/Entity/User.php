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
 *
 * @lkrms-sample-entity https://jsonplaceholder.typicode.com/users
 * @lkrms-generate-command lk-util generate sync entity --class='Lkrms\Tests\Sync\Entity\User' --visibility='protected' --provider='\Lkrms\Tests\Sync\Provider\JsonPlaceholderApi' --endpoint='/users'
 */
class User extends \Lkrms\Sync\SyncEntity
{
    /**
     * @var int|string|null
     */
    protected $Id;

    /**
     * @var string|null
     */
    protected $Name;

    /**
     * @var string|null
     */
    protected $Username;

    /**
     * @var string|null
     */
    protected $Email;

    /**
     * @var array|null
     */
    protected $Address;

    /**
     * @var string|null
     */
    protected $Phone;

    // Commented out for IExtensible / __clone() testing

    ///**
    // * @var string|null
    // */
    //protected $Website;

    /**
     * @var array|null
     */
    protected $Company;

    /**
     * @var Post[]|null
     */
    protected $Posts;

}
