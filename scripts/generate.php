#!/usr/bin/env php
<?php declare(strict_types=1);

use Salient\Cache\CacheStore;
use Salient\Catalog\Core\MessageLevel as Level;
use Salient\Cli\CliApplication;
use Salient\Cli\CliOption;
use Salient\Cli\CliOptionBuilder;
use Salient\Console\ConsoleWriter;
use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Core\Facade\App;
use Salient\Core\Facade\Cache;
use Salient\Core\Facade\Config;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Event;
use Salient\Core\Facade\Profile;
use Salient\Core\Facade\Sync;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Package;
use Salient\Core\Utility\Pcre;
use Salient\Core\ConfigurationManager;
use Salient\Core\ErrorHandler;
use Salient\Core\EventDispatcher;
use Salient\Core\MetricCollector;
use Salient\Curler\Support\CurlerPage;
use Salient\Curler\Support\CurlerPageBuilder;
use Salient\Curler\Curler;
use Salient\Curler\CurlerBuilder;
use Salient\Sli\Catalog\EnvVar;
use Salient\Sli\Command\Generate\Concept\GenerateCommand;
use Salient\Sli\Command\Generate\GenerateBuilder;
use Salient\Sli\Command\Generate\GenerateFacade;
use Salient\Sli\Command\Generate\GenerateSyncEntity;
use Salient\Sli\Command\Generate\GenerateSyncProvider;
use Salient\Sync\DbSyncDefinition;
use Salient\Sync\DbSyncDefinitionBuilder;
use Salient\Sync\HttpSyncDefinition;
use Salient\Sync\HttpSyncDefinitionBuilder;
use Salient\Sync\SyncError;
use Salient\Sync\SyncErrorBuilder;
use Salient\Sync\SyncSerializeRules;
use Salient\Sync\SyncSerializeRulesBuilder;
use Salient\Sync\SyncStore;
use Salient\Tests\Core\AbstractFacade\MyBrokenFacade;
use Salient\Tests\Core\AbstractFacade\MyClassFacade;
use Salient\Tests\Core\AbstractFacade\MyHasFacadeClass;
use Salient\Tests\Core\AbstractFacade\MyInterfaceFacade;
use Salient\Tests\Core\AbstractFacade\MyServiceClass;
use Salient\Tests\Core\AbstractFacade\MyServiceInterface;
use Salient\Tests\Sync\Entity\Album;
use Salient\Tests\Sync\Entity\Comment;
use Salient\Tests\Sync\Entity\Photo;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\Task;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;

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
    MyBrokenFacade::class => [MyServiceInterface::class, ['Salient\Tests\Core\AbstractFacade\MyNonExistentClass']],
    MyInterfaceFacade::class => [MyServiceInterface::class, ['Salient\Tests\Core\AbstractFacade\MyNonExistentClass', MyHasFacadeClass::class]],
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
        File::putContents($file, Json::prettyPrint($generateEntity->Entity));
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
if (File::getContents($file) !== $attributes) {
    if (in_array('--check', $args)) {
        Console::info('Would replace', $file);
        Console::count(Level::ERROR);
        $status |= 1;
    } else {
        Console::info('Replacing', $file);
        File::putContents($file, $attributes);
    }
} else {
    Console::log('Nothing to do:', $file);
}

Console::summary('Code generation completed');

exit($status);
