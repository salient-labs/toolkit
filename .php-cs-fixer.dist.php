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
        'is_null' => true,
        'native_constant_invocation' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'yoda_style' => ['equal' => false, 'identical' => false],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
