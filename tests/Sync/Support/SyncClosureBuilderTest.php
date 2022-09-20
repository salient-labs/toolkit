<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\SyncOperation;
use Lkrms\Tests\Sync\CustomEntity\User;
use Lkrms\Tests\Sync\Entity\UserProvider;

final class SyncClosureBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testGetSyncOperationMethod()
    {
        $entityClosureBuilder   = SyncClosureBuilder::get(User::class);
        $providerClosureBuilder = SyncClosureBuilder::get(UserProvider::class);

        $this->assertEquals("getUser", $providerClosureBuilder->getSyncOperationMethod(SyncOperation::READ, $entityClosureBuilder));
        $this->assertEquals("getUsers", $providerClosureBuilder->getSyncOperationMethod(SyncOperation::READ_LIST, $entityClosureBuilder));
        $this->assertEquals("createUser", $providerClosureBuilder->getSyncOperationMethod(SyncOperation::CREATE, $entityClosureBuilder));
        $this->assertEquals(null, $providerClosureBuilder->getSyncOperationMethod(SyncOperation::CREATE_LIST, $entityClosureBuilder));
    }

}
