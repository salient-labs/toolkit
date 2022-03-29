<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 *
 * @package Lkrms\Tests
 */
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

    /**
     * @var string
     */
    public $Website;

    /**
     * @var array
     */
    public $Company;
}

