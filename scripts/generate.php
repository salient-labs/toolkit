#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliOption;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\ConsoleWriter;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Curler\Support\CurlerPage;
use Lkrms\Curler\Curler;
use Lkrms\Facade\Console;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\LkUtil\Command\Generate\GenerateBuilder;
use Lkrms\LkUtil\Command\Generate\GenerateFacade;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntity;
use Lkrms\LkUtil\Command\Generate\GenerateSyncProvider;
use Lkrms\Store\CacheStore;
use Lkrms\Store\TrashStore;
use Lkrms\Support\ArrayMapper;
use Lkrms\Support\ErrorHandler;
use Lkrms\Support\EventDispatcher;
use Lkrms\Support\Timekeeper;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\SyncError;
use Lkrms\Sync\Support\SyncSerializeRules;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Tests\Sync\Entity\Album;
use Lkrms\Tests\Sync\Entity\Comment;
use Lkrms\Tests\Sync\Entity\Photo;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Task;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Debugging;
use Lkrms\Utility\File;
use Lkrms\Utility\Formatters;
use Lkrms\Utility\System;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';

$facades = [
    Application::class => \Lkrms\Facade\App::class,
    ArrayMapper::class => \Lkrms\Facade\Mapper::class,
    CacheStore::class => \Lkrms\Facade\Cache::class,
    ConsoleWriter::class => \Lkrms\Facade\Console::class,
    Container::class => \Lkrms\Facade\DI::class,
    Debugging::class => \Lkrms\Facade\Debug::class,
    ErrorHandler::class => [\Lkrms\Facade\Err::class, '--skip', 'handleShutdown,handleError,handleException'],
    EventDispatcher::class => \Lkrms\Facade\Event::class,
    Formatters::class => \Lkrms\Facade\Format::class,
    SyncStore::class => \Lkrms\Facade\Sync::class,
    Timekeeper::class => \Lkrms\Facade\Profile::class,
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

$entities = [
    Album::class => ['albums', '--one', 'User=User', '--many', 'Photos=Photo'],
    Comment::class => ['comments', '--one', 'Post=Post'],
    Photo::class => ['photos', '--one', 'Album=Album'],
    Post::class => ['posts', '--one', 'User=User', '--many', 'Comments=Comment'],
    Task::class => ['todos', '--one', 'User=User'],
    User::class => ['users', '--many', 'Tasks=Task,Posts=Post,Albums=Album', '--skip', 'Website'],
];

$providers = [
    Album::class => [],
    Comment::class => [],
    Photo::class => [],
    Post::class => [],
    Task::class => [],
    User::class => [],
];

$app = new CliApplication(dirname(__DIR__));
$generateFacade = new GenerateFacade($app);
$generateBuilder = new GenerateBuilder($app);
$generateEntity = new GenerateSyncEntity($app);
$generateProvider = new GenerateSyncProvider($app);

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

/**
 * @param GenerateCommand|string $commandOrFile
 */
function generated($commandOrFile): void
{
    global $generated;

    $file = $commandOrFile instanceof GenerateCommand
        ? $commandOrFile->OutputFile
        : $commandOrFile;

    if ($file === null) {
        throw new LogicException('No file generated');
    }

    $generated[] = '/' . File::relativeToParent($file);
}

$status = 0;
$generated = [];

foreach ($facades as $class => $facade) {
    $facadeArgs = [];
    if (is_array($facade)) {
        $facadeArgs = $facade;
        $facade = array_shift($facadeArgs);
    }
    $status |= $generateFacade(...[...$args, ...$facadeArgs, $class, $facade]);
    generated($generateFacade);
}

foreach ($builders as $class => $builder) {
    $builderArgs = [];
    if (is_array($builder)) {
        $builderArgs = $builder;
        $builder = array_shift($builderArgs);
    }
    $status |= $generateBuilder(...[...$args, ...$builderArgs, $class, $builder]);
    generated($generateBuilder);
}

foreach ($entities as $class => $entityArgs) {
    $entity = array_shift($entityArgs);
    $file = dirname(__DIR__) . "/tests/data/entity/{$entity}.json";
    $save = false;
    if (is_file($file)) {
        array_unshift($entityArgs, '--json', $file);
        generated($file);
    } else {
        array_unshift($entityArgs, '--provider', JsonPlaceholderApi::class, '--endpoint', "/{$entity}");
        $save = true;
    }
    $status |= $generateEntity(...[...$args, ...$entityArgs, $class]);
    generated($generateEntity);
    if ($save && $generateEntity->Entity !== null) {
        File::createDir(dirname($file));
        file_put_contents($file, json_encode($generateEntity->Entity, \JSON_PRETTY_PRINT));
        generated($file);
    }
}

foreach ($providers as $class => $providerArgs) {
    $status |= $generateProvider(...[...$args, ...$providerArgs, $class]);
    generated($generateProvider);
}

$file = dirname(__DIR__) . '/.gitattributes';
$attributes = preg_grep(
    '/(^#| linguist-generated$)/',
    Arr::notEmpty(Arr::trim(file($file))),
    \PREG_GREP_INVERT
);
// @phpstan-ignore-next-line
foreach ($generated as $generated) {
    $attributes[] = sprintf('%s linguist-generated', $generated);
}
sort($attributes);
$attributes = implode(\PHP_EOL, $attributes) . \PHP_EOL;
if (file_get_contents($file) !== $attributes) {
    if (in_array('--check', $args)) {
        Console::info('Would replace', $file);
        Console::count(Level::ERROR);
        $status |= 1;
    } else {
        Console::info('Replacing', $file);
        file_put_contents($file, $attributes);
    }
} else {
    Console::log('Nothing to do:', $file);
}

Console::summary('Code generation completed');

exit ($status);
