<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\File;

use Salient\Core\Utility\File;

require dirname(__DIR__, 6) . '/vendor/autoload.php';

echo File::getCwd() . \PHP_EOL;
