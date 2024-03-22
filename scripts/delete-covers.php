#!/usr/bin/env php
<?php declare(strict_types=1);

use Salient\Cli\CliApplication;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Core\Exception\FilesystemErrorException;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Pcre;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

$args = array_slice($_SERVER['argv'], 1);
$check = !in_array('--force', $args);

$files = File::find()
    ->in(dirname(__DIR__) . '/tests/unit')
    ->include('/Test\.php$/')
    ->toArray();

$count = count($files);
$replaced = 0;

$i = 0;
foreach ($files as $file) {
    $i++;
    $file = (string) $file;
    $relative = File::relativeToParent($file, dirname(__DIR__));
    Console::logProgress(sprintf('Checking %d of %d:', $i, $count), $relative);

    $input = @file($file);
    if ($input === false) {
        $error = error_get_last();
        throw new FilesystemErrorException($error['message'] ?? sprintf(
            'Error reading file: %s',
            $file,
        ));
    }

    $output = Pcre::grep('/^ ++\* @covers(?:Nothing)?\b/i', $input, \PREG_GREP_INVERT);
    if ($output === $input) {
        Console::log('Nothing to do:', $relative);
        continue;
    }

    $replaced++;

    if ($check) {
        $diff = (new Differ(new StrictUnifiedDiffOutputBuilder([
            'fromFile' => "a/$relative",
            'toFile' => "b/$relative",
        ])))->diff($input, $output);
        Console::maybeClearLine();
        $formatter ??= Console::getStdoutTarget()->getFormatter();
        print $formatter->formatDiff($diff);
        continue;
    }

    Console::info('Replacing', $relative);
    File::putContents($file, $output);
}

if ($replaced) {
    Console::out('', Level::INFO);
}

Console::summary(Inflect::format(
    $count,
    '@covers removed from %d of {{#}} {{#:test}}',
    $replaced,
), '', true);
