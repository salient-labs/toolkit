#!/usr/bin/env php
<?php

use Composer\InstalledVersions;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\TypeParserTest;
use Salient\Utility\Get;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';

// Delete `vendor/phpstan/phpdoc-parser` and run the following command before
// running this script:
//
// composer require --dev --prefer-source phpstan/phpdoc-parser=^2
$loader->addPsr4(
    'PHPStan\\PhpDocParser\\',
    InstalledVersions::getInstallPath('phpstan/phpdoc-parser') . '/tests/PHPStan/'
);

// @phpstan-ignore class.notFound
$typeParserTest = new TypeParserTest();

$data = [];
foreach ([
    // @phpstan-ignore class.notFound
    $typeParserTest->provideParseData(),
    // @phpstan-ignore class.notFound
    $typeParserTest->dataLinesAndIndexes(),
] as $tests) {
    foreach ($tests as $test) {
        if ($test[1] instanceof ParserException) {
            $data[$test[0]] = [$test[0], false];
            continue;
        }
        $data[$test[0]] = [$test[0]];
    }
}

printf(
    '%s' . \PHP_EOL,
    Get::code(array_values($data), ', ', ' => ', null, '    ', [], [\PHP_EOL => '\PHP_EOL']),
);
