<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/stubs',
        __DIR__ . '/tests/unit',
        __DIR__ . '/lk-util',
        __DIR__ . '/scripts',
    ])
    ->append([
        __DIR__ . '/bin/lk-util',
        __DIR__ . '/bootstrap.php',
        __DIR__ . '/tests/phpstan-conditional.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        'native_constant_invocation' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
