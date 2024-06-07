<?php declare(strict_types=1);

namespace Salient\Tests\Utility\File;

use Salient\Utility\File;

require dirname(__DIR__, 5) . '/vendor/autoload.php';

echo File::getcwd() . \PHP_EOL;
