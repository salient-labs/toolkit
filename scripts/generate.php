#!/usr/bin/env php
<?php declare(strict_types=1);

use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionBuilder;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\ConsoleWriter;
use Lkrms\Container\Container;
use Lkrms\Container\ContainerInterface;
use Lkrms\Curler\Support\CurlerPage;
use Lkrms\Curler\Support\CurlerPageBuilder;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Facade\App;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Config;
use Lkrms\Facade\Console;
use Lkrms\Facade\Err;
use Lkrms\Facade\Event;
use Lkrms\Facade\Profile;
use Lkrms\Facade\Sync;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use Lkrms\LkUtil\Command\Generate\GenerateBuilder;
use Lkrms\LkUtil\Command\Generate\GenerateFacade;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntity;
use Lkrms\LkUtil\Command\Generate\GenerateSyncProvider;
use Lkrms\Store\CacheStore;
use Lkrms\Support\ErrorHandler;
use Lkrms\Support\EventDispatcher;
use Lkrms\Support\MetricCollector;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\DbSyncDefinitionBuilder;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Sync\Support\SyncError;
use Lkrms\Sync\Support\SyncErrorBuilder;
use Lkrms\Sync\Support\SyncSerializeRules;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Tests\Concept\Facade\MyBrokenFacade;
use Lkrms\Tests\Concept\Facade\MyClassFacade;
use Lkrms\Tests\Concept\Facade\MyHasFacadeClass;
use Lkrms\Tests\Concept\Facade\MyInterfaceFacade;
use Lkrms\Tests\Concept\Facade\MyServiceClass;
use Lkrms\Tests\Concept\Facade\MyServiceInterface;
use Lkrms\Tests\Sync\Entity\Album;
use Lkrms\Tests\Sync\Entity\Comment;
use Lkrms\Tests\Sync\Entity\Photo;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Task;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use Lkrms\Utility\Json;
use Lkrms\Utility\Package;
use Lkrms\Utility\Pcre;
use Salient\Core\ConfigurationManager;

require dirname(__DIR__) . '/vendor/autoload.php';

$facades = [
    App::class => [ContainerInterface::class, [Container::class], '--desc', 'A facade for the global service container', '--api'],
    Cache::class => CacheStore::class,
    Config::class => [ConfigurationManager::class, '--api'],
    Console::class => [ConsoleWriter::class, '--api'],
    Err::class => [ErrorHandler::class, '--skip', 'handleShutdown,handleError,handleException'],
    Event::class => EventDispatcher::class,
    Sync::class => SyncStore::class,
    Profile::class => [MetricCollector::class, '--api'],
    // Test fixtures
    MyBrokenFacade::class => [MyServiceInterface::class, ['Lkrms\Tests\Concept\Facade\MyNonExistentClass']],
    MyInterfaceFacade::class => [MyServiceInterface::class, ['Lkrms\Tests\Concept\Facade\MyNonExistentClass', MyHasFacadeClass::class]],
    MyClassFacade::class => [MyServiceClass::class, '--skip', 'withArgs'],
];

$builders = [
    CliOption::class => [CliOptionBuilder::class, '--forward=load', '--api'],
    Curler::class => [CurlerBuilder::class, '--forward', '--skip', 'responseContentTypeIs,getQueryUrl'],
    CurlerPage::class => CurlerPageBuilder::class,
    DbSyncDefinition::class => DbSyncDefinitionBuilder::class,
    HttpSyncDefinition::class => HttpSyncDefinitionBuilder::class,
    SyncError::class => SyncErrorBuilder::class,
    SyncSerializeRules::class => SyncSerializeRulesBuilder::class,
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
    Env::unset($constant->getValue());
}

$args = [
    '--force',
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

    $generated[] = '/' . File::relativeToParent($file, Package::path());
}

$status = 0;
$generated = [];

foreach ($facades as $facade => $class) {
    $facadeArgs = [];
    $aliases = [];
    if (is_array($class)) {
        $facadeArgs = $class;
        $class = array_shift($facadeArgs);
        if (is_array(reset($facadeArgs))) {
            $aliases = array_shift($facadeArgs);
        }
    }
    $status |= $generateFacade(...[...$args, ...$facadeArgs, $class, ...$aliases, $facade]);
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
        file_put_contents($file, Json::prettyPrint($generateEntity->Entity));
        generated($file);
    }
}

foreach ($providers as $class => $providerArgs) {
    $status |= $generateProvider(...[...$args, ...$providerArgs, $class]);
    generated($generateProvider);
}

$file = dirname(__DIR__) . '/.gitattributes';
$attributes = Pcre::grep(
    '/(^#| linguist-generated$)/',
    Arr::trim(file($file)),
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

exit($status);
