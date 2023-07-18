#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliOption;
use Lkrms\Console\ConsoleWriter;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Curler\Curler;
use Lkrms\Curler\Support\CurlerPage;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\GenerateBuilder;
use Lkrms\LkUtil\Command\Generate\GenerateFacade;
use Lkrms\Store\CacheStore;
use Lkrms\Store\TrashStore;
use Lkrms\Support\ArrayMapper;
use Lkrms\Support\EventDispatcher;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\SyncError;
use Lkrms\Sync\Support\SyncSerializeRules;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Assertions;
use Lkrms\Utility\Composer;
use Lkrms\Utility\Computations;
use Lkrms\Utility\Debugging;
use Lkrms\Utility\Filesystem;
use Lkrms\Utility\Formatters;
use Lkrms\Utility\Reflection;
use Lkrms\Utility\System;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Lkrms\\LkUtil\\', dirname(__DIR__) . '/lib/lk-util');

$facades = [
    Application::class => \Lkrms\Facade\App::class,
    ArrayMapper::class => \Lkrms\Facade\Mapper::class,
    Assertions::class => \Lkrms\Facade\Assert::class,
    CacheStore::class => \Lkrms\Facade\Cache::class,
    Composer::class => \Lkrms\Facade\Composer::class,
    Computations::class => \Lkrms\Facade\Compute::class,
    ConsoleWriter::class => \Lkrms\Facade\Console::class,
    Container::class => \Lkrms\Facade\DI::class,
    Debugging::class => \Lkrms\Facade\Debug::class,
    EventDispatcher::class => \Lkrms\Facade\Event::class,
    Filesystem::class => \Lkrms\Facade\File::class,
    Formatters::class => \Lkrms\Facade\Format::class,
    Reflection::class => \Lkrms\Facade\Reflect::class,
    SyncStore::class => \Lkrms\Facade\Sync::class,
    System::class => \Lkrms\Facade\Sys::class,
    TrashStore::class => \Lkrms\Facade\Trash::class,
];

$builders = [
    CliOption::class => \Lkrms\Cli\CliOptionBuilder::class,
    Curler::class => \Lkrms\Curler\CurlerBuilder::class,
    CurlerPage::class => \Lkrms\Curler\Support\CurlerPageBuilder::class,
    DbSyncDefinition::class => \Lkrms\Sync\Support\DbSyncDefinitionBuilder::class,
    HttpSyncDefinition::class => \Lkrms\Sync\Support\HttpSyncDefinitionBuilder::class,
    SyncError::class => \Lkrms\Sync\Support\SyncErrorBuilder::class,
    SyncSerializeRules::class => \Lkrms\Sync\Support\SyncSerializeRulesBuilder::class,
];

$app = new CliApplication(dirname(__DIR__));
$generateFacade = new GenerateFacade($app);
$generateBuilder = new GenerateBuilder($app);

$class = new ReflectionClass(EnvVar::class);
foreach ($class->getReflectionConstants() as $constant) {
    if (!$constant->isPublic()) {
        continue;
    }
    $app->env()->unset($constant->getValue());
}

foreach ($facades as $class => $facade) {
    $generateFacade('-f', '-m', $class, $facade);
}

foreach ($builders as $class => $builder) {
    $generateBuilder('-f', '-m', $class, $builder);
}
