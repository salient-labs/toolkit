#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliOption;
use Lkrms\Console\ConsoleWriter;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Curler\Support\CurlerPage;
use Lkrms\Curler\Curler;
use Lkrms\Facade\Console;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\GenerateBuilder;
use Lkrms\LkUtil\Command\Generate\GenerateFacade;
use Lkrms\Store\CacheStore;
use Lkrms\Store\TrashStore;
use Lkrms\Support\ArrayMapper;
use Lkrms\Support\ErrorHandler;
use Lkrms\Support\EventDispatcher;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\SyncError;
use Lkrms\Sync\Support\SyncSerializeRules;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Assertions;
use Lkrms\Utility\Composer;
use Lkrms\Utility\Debugging;
use Lkrms\Utility\Filesystem;
use Lkrms\Utility\Formatters;
use Lkrms\Utility\System;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->addPsr4('Lkrms\\LkUtil\\', dirname(__DIR__) . '/lib/lk-util');

$facades = [
    Application::class => \Lkrms\Facade\App::class,
    ArrayMapper::class => \Lkrms\Facade\Mapper::class,
    Assertions::class => \Lkrms\Facade\Assert::class,
    CacheStore::class => \Lkrms\Facade\Cache::class,
    Composer::class => \Lkrms\Facade\Composer::class,
    ConsoleWriter::class => \Lkrms\Facade\Console::class,
    Container::class => \Lkrms\Facade\DI::class,
    Debugging::class => \Lkrms\Facade\Debug::class,
    ErrorHandler::class => [\Lkrms\Facade\Err::class, '--skip', 'handleShutdown,handleError,handleException'],
    EventDispatcher::class => \Lkrms\Facade\Event::class,
    Filesystem::class => \Lkrms\Facade\File::class,
    Formatters::class => \Lkrms\Facade\Format::class,
    SyncStore::class => \Lkrms\Facade\Sync::class,
    System::class => \Lkrms\Facade\Sys::class,
    TrashStore::class => \Lkrms\Facade\Trash::class,
];

$builders = [
    CliOption::class => \Lkrms\Cli\CliOptionBuilder::class,
    Curler::class => [\Lkrms\Curler\CurlerBuilder::class, '--forward', '--skip', 'responseContentTypeIs,getQueryUrl'],
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

$args = [
    '--force',
    '--no-meta',
    ...array_slice($_SERVER['argv'], 1),
];

$status = 0;

foreach ($facades as $class => $facade) {
    $facadeArgs = [];
    if (is_array($facade)) {
        $facadeArgs = $facade;
        $facade = array_shift($facadeArgs);
    }
    $status |= $generateFacade(...[...$args, ...$facadeArgs, $class, $facade]);
}

foreach ($builders as $class => $builder) {
    $builderArgs = [];
    if (is_array($builder)) {
        $builderArgs = $builder;
        $builder = array_shift($builderArgs);
    }
    $status |= $generateBuilder(...[...$args, ...$builderArgs, $class, $builder]);
}

Console::summary('Code generation completed');

exit ($status);
