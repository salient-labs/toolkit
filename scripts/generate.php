#!/usr/bin/env php
<?php declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Salient\Cache\CacheStore;
use Salient\Cli\CliApplication;
use Salient\Cli\CliOption;
use Salient\Cli\CliOptionBuilder;
use Salient\Console\Console as ConsoleService;
use Salient\Container\Container;
use Salient\Contract\Cache\CacheInterface;
use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Event\EventDispatcherInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Event\EventDispatcher;
use Salient\Core\Facade\App;
use Salient\Core\Facade\Cache;
use Salient\Core\Facade\Config;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Event;
use Salient\Core\Facade\Profile;
use Salient\Core\Facade\Sync;
use Salient\Core\ConfigurationManager;
use Salient\Core\ErrorHandler;
use Salient\Core\MetricCollector;
use Salient\Curler\Curler;
use Salient\Curler\CurlerBuilder;
use Salient\Sli\Command\Generate\AbstractGenerateCommand;
use Salient\Sli\Command\Generate\GenerateBuilder;
use Salient\Sli\Command\Generate\GenerateFacade;
use Salient\Sli\Command\Generate\GenerateSyncEntity;
use Salient\Sli\Command\Generate\GenerateSyncProvider;
use Salient\Sli\Command\AnalyseClass;
use Salient\Sli\EnvVar;
use Salient\Sync\Db\DbSyncDefinition;
use Salient\Sync\Db\DbSyncDefinitionBuilder;
use Salient\Sync\Http\HttpSyncDefinition;
use Salient\Sync\Http\HttpSyncDefinitionBuilder;
use Salient\Sync\SyncError;
use Salient\Sync\SyncErrorBuilder;
use Salient\Sync\SyncSerializeRules;
use Salient\Sync\SyncSerializeRulesBuilder;
use Salient\Sync\SyncStore;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\Core\Facade\MyBrokenFacade;
use Salient\Tests\Core\Facade\MyClassFacade;
use Salient\Tests\Core\Facade\MyFacadeAwareInstanceClass;
use Salient\Tests\Core\Facade\MyInterfaceFacade;
use Salient\Tests\Core\Facade\MyServiceClass;
use Salient\Tests\Core\Facade\MyServiceInterface;
use Salient\Tests\Sli\Command\AnalyseClassTest;
use Salient\Tests\Sync\Entity\Album;
use Salient\Tests\Sync\Entity\Comment;
use Salient\Tests\Sync\Entity\Photo;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\Task;
use Salient\Tests\Sync\Entity\Unimplemented;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Package;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;

$dir = dirname(__DIR__);
/** @var ClassLoader */
$loader = require "$dir/vendor/autoload.php";

$loader->addPsr4('Salient\\Tests\\', ["$dir/tests/unit/Toolkit/", "$dir/tests/fixtures/Toolkit/"]);

$facades = [
    App::class => [ContainerInterface::class, [Container::class], '--desc', 'A facade for the global service container', '--api'],
    Cache::class => [CacheInterface::class, [CacheStore::class], '--desc', 'A facade for the global cache', '--api'],
    Config::class => [ConfigurationManager::class, '--api'],
    Console::class => [ConsoleInterface::class, [ConsoleService::class], '--desc', 'A facade for the global console service', '--api'],
    Err::class => [ErrorHandler::class, '--skip', 'handleShutdown,handleError,handleException', '--api'],
    Event::class => [EventDispatcherInterface::class, [EventDispatcher::class], '--desc', 'A facade for the global event dispatcher', '--api'],
    Sync::class => [SyncStoreInterface::class, [SyncStore::class], '--desc', 'A facade for the global sync entity store'],
    Profile::class => [MetricCollector::class, '--api'],
    // Test fixtures
    MyBrokenFacade::class => [MyServiceInterface::class, ['Salient\Tests\Core\Facade\MyNonExistentClass']],
    MyInterfaceFacade::class => [MyServiceInterface::class, ['Salient\Tests\Core\Facade\MyNonExistentClass', MyFacadeAwareInstanceClass::class]],
    MyClassFacade::class => [MyServiceClass::class, '--skip', 'withArgs'],
];

$builders = [
    CliOption::class => [CliOptionBuilder::class, '--forward=load', '--no-declare=valueCallback', '--desc', '', '--api'],
    Curler::class => [CurlerBuilder::class, '--forward=head,get,post,put,patch,delete,getP,postP,putP,patchP,deleteP,postR,putR,patchR,deleteR', '--desc', '', '--api'],
    DbSyncDefinition::class => [DbSyncDefinitionBuilder::class, '--no-declare=overrides', '--desc', ''],
    HttpSyncDefinition::class => [HttpSyncDefinitionBuilder::class, '--no-declare=callback,curlerCallback,overrides', '--desc', ''],
    SyncError::class => [SyncErrorBuilder::class, '--desc', ''],
    SyncSerializeRules::class => [SyncSerializeRulesBuilder::class, '--no-declare=remove,replace', '--desc', ''],
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
    Unimplemented::class => [],
    Salient\Tests\Sync\Entity\Collides::class => [],
    Salient\Tests\Sync\External\Entity\Collides::class => [],
];

$data = [
    'unicode/ucd/ReadMe.txt' =>
        'https://www.unicode.org/Public/UCD/latest/ucd/ReadMe.txt',
    'unicode/ucd/DerivedCoreProperties.txt' =>
        'https://www.unicode.org/Public/UCD/latest/ucd/DerivedCoreProperties.txt',
    'unicode/ucd/extracted/DerivedGeneralCategory.txt' =>
        'https://www.unicode.org/Public/UCD/latest/ucd/extracted/DerivedGeneralCategory.txt',
];

$commands = [
    AnalyseClass::class => AnalyseClassTest::runProvider(),
];

$app = new CliApplication($dir);
$generateFacade = new GenerateFacade($app);
$generateBuilder = new GenerateBuilder($app);
$generateEntity = new GenerateSyncEntity($app);
$generateProvider = new GenerateSyncProvider($app);

/** @var string $name */
foreach (Reflect::getConstants(EnvVar::class) as $name) {
    Env::unset($name);
}

/** @var string[] */
$args = $_SERVER['argv'];
$args = [
    '--collapse',
    '--force',
    ...array_slice($args, 1),
];

$online = array_search('--online', $args);
if ($online !== false) {
    unset($args[$online]);
    $online = true;
}

/**
 * @param AbstractGenerateCommand|string $commandOrFile
 */
function generated($commandOrFile): void
{
    global $generated;

    $file = $commandOrFile instanceof AbstractGenerateCommand
        ? $commandOrFile->OutputFile
        : $commandOrFile;

    if ($file === null) {
        throw new LogicException('No file generated');
    }

    $generated[] = '/' . File::getRelativePath($file, Package::path());
}

$status = 0;
$generated = [];

foreach ($facades as $facade => $class) {
    $facadeArgs = [];
    $aliases = [];
    // if (is_array($class)) {
    $facadeArgs = $class;
    $class = array_shift($facadeArgs);
    if (is_array($facadeArgs[0])) {
        $aliases = array_shift($facadeArgs);
    }
    // }
    $status |= $generateFacade(...[...$args, ...$facadeArgs, $class, ...$aliases, $facade]);
    generated($generateFacade);
}

foreach ($builders as $class => $builder) {
    $builderArgs = [];
    // if (is_array($builder)) {
    $builderArgs = $builder;
    $builder = array_shift($builderArgs);
    // }
    $status |= $generateBuilder(...[...$args, ...$builderArgs, $class, $builder]);
    generated($generateBuilder);
}

foreach ($entities as $class => $entityArgs) {
    $entity = array_shift($entityArgs);
    $file = "$dir/tests/data/entity/{$entity}.json";
    $save = false;
    if (is_file($file)) {
        array_unshift($entityArgs, '--json', $file);
        generated($file);
    } else {
        array_unshift($entityArgs, '--provider', JsonPlaceholderApi::class, '--endpoint', "/$entity");
        $save = true;
    }
    $status |= $generateEntity(...[...$args, ...$entityArgs, $class]);
    generated($generateEntity);
    if ($save && $generateEntity->Entity !== null) {
        File::createDir(dirname($file));
        File::writeContents($file, Json::prettyPrint($generateEntity->Entity));
        generated($file);
    }
}

foreach ($providers as $class => $providerArgs) {
    $status |= $generateProvider(...[...$args, ...$providerArgs, $class]);
    generated($generateProvider);
}

foreach ($data as $file => $uri) {
    $file = "$dir/tests/data/$file";
    $exists = file_exists($file);
    if ($exists && !$online) {
        Console::log('Skipping', $file);
    } elseif (!$exists && in_array('--check', $args)) {
        Console::info('Would create', $file);
        Console::count(Level::ERROR);
        $status |= 1;
        continue;
    } elseif (!in_array('--check', $args)) {
        Console::log('Downloading', $uri);
        $content = File::getContents($uri);
        if (!$exists || File::getContents($file) !== $content) {
            Console::info('Replacing', $file);
            File::createDir(dirname($file));
            File::writeContents($file, $content);
        } else {
            Console::log('Nothing to do:', $file);
        }
    }
    generated($file);
}

foreach (
    File::find()
        ->in($dir)
        ->include('%/phpstan-baseline.*\.neon$%')
        ->doNotRecurse() as $file
) {
    generated((string) $file);
}

$debug = Env::getDebug();
$mockTarget = null;
foreach ($commands as $class => $tests) {
    $command = new $class($app);
    foreach ($tests as $dataSet => $test) {
        [$file, $exitStatus, $commandArgs] = $test;
        $exists = file_exists($file);
        if (in_array('--check', $args)) {
            if ($exists) {
                generated($file);
            }
            continue;
        }
        if (isset($test[3])) {
            File::chdir($test[3]);
        }
        if (!$debug) {
            foreach (Console::getTargets() as $target) {
                Console::deregisterTarget($target);
            }
            Console::registerTarget($mockTarget = new MockTarget());
        }
        ob_start();
        $result = $command(...$commandArgs);
        /** @var string */
        $content = ob_get_clean();
        if ($mockTarget) {
            Console::deregisterTarget($mockTarget);
            Console::registerStdioTargets();
        }
        if ($result !== $exitStatus) {
            $status |= 1;
            Console::error(
                sprintf('Exit status %d (expected %d):', $result, $exitStatus),
                sprintf('%s with data set %s', $class, is_int($dataSet) ? "#$dataSet" : "\"$dataSet\""),
            );
            if ($exists) {
                generated($file);
            }
            continue;
        }
        if (!$exists || File::getContents($file) !== $content) {
            Console::info('Replacing', $file);
            File::createDir(dirname($file));
            File::writeContents($file, $content);
        } else {
            Console::log('Nothing to do:', $file);
        }
        generated($file);
    }
}

$file = "$dir/.gitattributes";
$attributes = Regex::grep(
    '/(^#| linguist-generated$)/',
    Arr::trim(File::getLines($file)),
    \PREG_GREP_INVERT
);
// @phpstan-ignore foreach.emptyArray
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
        File::writeContents($file, $attributes);
    }
} else {
    Console::log('Nothing to do:', $file);
}

Console::summary('Code generation completed');

exit($status);
