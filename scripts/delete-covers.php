#!/usr/bin/env php
<?php declare(strict_types=1);

use Salient\Cli\CliApplication;
use Salient\Core\Facade\Console;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\File;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

/** @var string[] */
$args = $_SERVER['argv'];
$args = array_slice($args, 1);
$check = !in_array('--force', $args);

$files = File::find()
    ->files()
    ->in(dirname(__DIR__) . '/tests/unit')
    ->include('/Test\.php$/')
    ->toArray();

$count = count($files);
$replaced = 0;
$status = 0;

$i = 0;
foreach ($files as $file) {
    $i++;
    $file = (string) $file;
    $relative = File::getRelativePath($file, dirname(__DIR__));
    Console::logProgress(sprintf('Checking %d of %d:', $i, $count), $relative);

    $input = @file($file);
    if ($input === false) {
        $error = error_get_last();
        throw new FilesystemErrorException($error['message'] ?? sprintf(
            'Error reading file: %s',
            $file,
        ));
    }

    $output = Regex::grep('/^(?: ++| *+\/\*)\* @covers(?:Nothing)?\b/i', $input, \PREG_GREP_INVERT);
    if ($output === $input) {
        Console::log('Nothing to do:', $relative);
        continue;
    }

    $replaced++;

    if ($check) {
        Console::count(Console::LEVEL_ERROR);
        $status |= 1;
        if (!class_exists(Differ::class)) {
            Console::log('Install sebastian/diff to show changes');
            Console::info('Would replace', $relative);
            continue;
        }
        $diff = (new Differ(new StrictUnifiedDiffOutputBuilder([
            'fromFile' => "a/$relative",
            'toFile' => "b/$relative",
        ])))->diff($input, $output);
        Console::clearProgress();
        $formatter ??= Console::getStdoutTarget()->getFormatter();
        print $formatter->formatDiff($diff);
        continue;
    }

    Console::info('Replacing', $relative);
    File::writeContents($file, $output);
}

if ($replaced) {
    Console::printStdio('', Console::LEVEL_INFO);
}

Console::summary(Inflect::format(
    $count,
    '@covers removed from %d of {{#}} {{#:test}}',
    $replaced,
), '', true);

exit($status);
