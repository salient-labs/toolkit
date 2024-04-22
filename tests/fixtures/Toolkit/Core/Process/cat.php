<?php declare(strict_types=1);

namespace Salient\Tests\Core\Process;

use Salient\Core\Utility\File;
use Salient\Tests\Core\ProcessTest;

require dirname(__DIR__, 5) . '/vendor/autoload.php';

$action = $_SERVER['argv'][1] ?? 'cat';

switch ($action) {
    case 'print-env':
        ProcessTest::forEachEnv(
            fn(string $key, string $value) =>
                fprintf(\STDOUT, '%s=%s' . \PHP_EOL, $key, $value)
        );
        break;

    case 'print-args':
        $stream = \STDOUT;
        // No break
    case 'cat':
    case 'delay':
    case 'timeout':
    default:
        $stream ??= \STDERR;
        foreach (array_slice($_SERVER['argv'], 1) as $i => $arg) {
            fprintf($stream, '- %d: %s' . \PHP_EOL, $i + 1, $arg);
        }

        if ($action === 'print-args') {
            break;
        }

        fprintf(\STDOUT, '%s', stream_get_contents(\STDIN));

        if ($action === 'delay') {
            File::close(\STDOUT);
            File::close(\STDERR);
            usleep(100000);
            exit(2);
        }

        if ($action === 'timeout') {
            sleep(120);
        }

        break;
}
