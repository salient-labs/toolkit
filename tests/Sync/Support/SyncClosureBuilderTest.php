<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\SyncOperation;
use Lkrms\Tests\Sync\CustomEntity\User;
use Lkrms\Tests\Sync\Entity\UserProvider;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use ReflectionFunction;

final class SyncClosureBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testGetSyncOperationMethod()
    {
        $provider = new JsonPlaceholderApi(new Container());
        $entityClosureBuilder   = SyncClosureBuilder::get(User::class);
        $providerClosureBuilder = SyncClosureBuilder::get(UserProvider::class);

        $this->assertEquals("getUser", $this->getMethodVar($providerClosureBuilder->getSyncOperationClosure(SyncOperation::READ, $entityClosureBuilder, $provider)));
        $this->assertEquals("getUsers", $this->getMethodVar($providerClosureBuilder->getSyncOperationClosure(SyncOperation::READ_LIST, $entityClosureBuilder, $provider)));
        $this->assertEquals("createUser", $this->getMethodVar($providerClosureBuilder->getSyncOperationClosure(SyncOperation::CREATE, $entityClosureBuilder, $provider)));
        $this->assertEquals(null, $providerClosureBuilder->getSyncOperationClosure(SyncOperation::CREATE_LIST, $entityClosureBuilder, $provider));
    }

    private function getMethodVar(Closure $closure): string
    {
        return (new ReflectionFunction($closure))->getStaticVariables()["method"];
    }

}
