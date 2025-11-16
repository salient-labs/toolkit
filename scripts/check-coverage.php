#!/usr/bin/env php
<?php declare(strict_types=1);

use Salient\Cli\CliApplication;
use Salient\Utility\Exception\FilesystemErrorException;
use SebastianBergmann\CodeCoverage\Node\Directory;
use SebastianBergmann\CodeCoverage\CodeCoverage;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new CliApplication(dirname(__DIR__));

/** @var string[] */
$args = $_SERVER['argv'];
$file = $args[1] ?? dirname(__DIR__) . '/build/coverage.php';
if (!is_file($file)) {
    throw new FilesystemErrorException(sprintf('File not found: %s', $file));
}

/** @var CodeCoverage */
$coverage = require ($file);

// [ Directory => [ name, API state, `Contract` state, code state ], ... ]
$components = [
    'Contract' => ['Contracts', false, false, false],  // 2025-02-22: top-level interfaces finalised
    'Utility' => ['Utils', true, false, '2025-01-28: code review finalised'],
    'Polyfill' => ['Polyfills', false, true, '2025-03-04: code review finalised'],
    'Collection' => ['Collections', true, true, '2025-02-23: code review finalised'],
    'Core' => ['Core', true, true, '2025-02-14: code review finalised, replacement of `Legacy` classes still pending'],
    'Iterator' => ['Iterators', true, true, '2025-02-26: code review finalised'],
    'Cache' => ['Cache', true, true, '2025-02-23: code review finalised'],
    'Console' => ['Console', true, true, '2025-03-28: code review finalised except `Formatter` rewrite'],
    'Container' => ['Container', true, true, '2025-03-11: code review finalised except `Container` rewrite sans Dice'],
    'Http' => ['HTTP', null, true, null],
    'Db' => ['Db', null, null, null],
    'Cli' => ['CLI', null, null, null],
    'Sync' => ['Sync', null, null, null],
    'Curler' => ['Curler', true, true, '2025-02-24: code review finalised'],
    'PHPDoc' => ['PHPDoc', null, false, null],
    'PHPStan' => ['PHPStan', false, false, '2025-03-04: code review finalised'],
    'Testing' => ['Testing', true, false, '2025-03-04: code review finalised'],
    'Sli' => ['Sli', null, false, null],
];

/**
 * @return array{coverage:float,executed:int,executable:int,code:int,comments:int,total:int}
 */
function getLines(Directory $dir): array
{
    $lines = $dir->linesOfCode();
    $lines = [
        'coverage' => 1.0,
        'executed' => $dir->numberOfExecutedLines(),
        'executable' => $dir->numberOfExecutableLines(),
        'code' => $lines['nonCommentLinesOfCode'],
        'comments' => $lines['commentLinesOfCode'],
        'total' => $lines['linesOfCode'],
    ];
    if ($lines['executable'] > 0) {
        $lines['coverage'] = $lines['executed'] / $lines['executable'];
    }
    return $lines;
}

/**
 * @param non-empty-array<non-empty-array<string|int>> $table
 */
function printTable(array $table): void
{
    foreach ($table as $row) {
        foreach ($row as $i => $column) {
            $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $column));
        }
    }

    $row = [];
    foreach ($widths as $width) {
        $row[] = str_repeat('-', $width);
    }
    array_splice($table, 1, 0, [$row]);

    foreach ($table as $row) {
        printf('|');
        foreach ($row as $i => $column) {
            printf(
                ' %s%s |',
                $column,
                str_repeat(' ', $widths[$i] - mb_strlen((string) $column)),
            );
        }
        printf("\n");
    }
}

$report = $coverage->getReport();
$totalLines = getLines($report);
$dirLines = [];
/** @var Directory $dir */
foreach ($report->directories() as $dir) {
    $dirLines[$dir->name()] = getLines($dir);
}

$data = [['Component', 'Size', 'Coverage', 'Lines', 'Code', 'Executable', 'API?', 'Contracts?', 'Code?']];
$totalSize = 0;
$progress = [0, 0, 0];
$expectedProgress = [0, 0, 0];
foreach ($components as $dir => $component) {
    $lines = $dirLines[$dir];
    /** @var string */
    $name = array_shift($component);
    $size = $lines['code'] / $totalLines['code'];
    $totalSize += $size;
    $row = [
        $name,
        sprintf('%.2f%%', $size * 100),
        sprintf('%.2f%%', $lines['coverage'] * 100),
        $lines['total'],
        $lines['code'],
        $lines['executable'],
    ];
    $size = $lines['total'] / $totalLines['total'];
    foreach ($component as $i => $state) {
        $row[] = $state === null
            ? '✘'
            : ($state === false
                ? '-'
                : '✔');
        if ($state !== false) {
            $expectedProgress[$i] += $size;
            if ($state !== null) {
                $progress[$i] += $size;
            }
        }
    }
    $data[] = $row;
}

$row = [
    'TOTAL',
    sprintf('%.2f%%', $totalSize * 100),
    sprintf('%.2f%%', $totalLines['coverage'] * 100),
    $totalLines['total'],
    $totalLines['code'],
    $totalLines['executable'],
];
foreach ($progress as $i => $actualProgress) {
    $row[] = sprintf('%.2f%%', $actualProgress * 100 / $expectedProgress[$i]);
}
$data[] = $row;

printTable($data);
