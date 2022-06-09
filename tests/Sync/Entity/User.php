<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

class User extends \Lkrms\Sync\SyncEntity
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
     * @var string
     */
    public $Phone;

    // Commented out for IExtensible / __clone() testing

    ///**
    // * @var string
    // */
    //public $Website;

    /**
     * @var array
     */
    public $Company;
}
