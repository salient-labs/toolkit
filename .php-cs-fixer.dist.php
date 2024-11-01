<?php

$customTokens = [
    'T_PROPERTY_C',
    'T_OPEN_BRACE',
    'T_OPEN_BRACKET',
    'T_OPEN_PARENTHESIS',
    'T_CLOSE_BRACE',
    'T_CLOSE_BRACKET',
    'T_CLOSE_PARENTHESIS',
    'T_AND',
    'T_COMMA',
    'T_SEMICOLON',
    'T_EQUAL',
];

/** @disregard P1009 */
$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests/unit',
        __DIR__ . '/tests/fixtures',
        __DIR__ . '/tests/3rdparty',
        __DIR__ . '/tests/stubs',
        __DIR__ . '/scripts',
        __DIR__ . '/tools/apigen/src',
    ])
    ->append([
        __DIR__ . '/src/Toolkit/Sli/sli',
        __DIR__ . '/tests/bootstrap.php',
        __DIR__ . '/tests/phpstan-conditional.php',
        __DIR__ . '/tests/test-sli',
        __DIR__ . '/tests/stubs/ADOConnection.stub',
        __DIR__ . '/tests/stubs/ADORecordSet.stub',
        __DIR__ . '/.php-cs-fixer.dist.php',
    ]);

/** @disregard P1009 */
return (new PhpCsFixer\Config())
    ->setRules([
        'fully_qualified_strict_types' => true,
        'is_null' => true,
        'native_constant_invocation' => ['include' => $customTokens],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'no_unneeded_import_alias' => true,
        'no_unused_imports' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => ['order' => [
            'todo',
            'property',
            'property-read',
            'property-write',
            'method',
            'api',
            'internal',
            'requires',
            'dataProvider',
            'backupGlobals',
            'template',
            'template-covariant',
            'extends',
            'implements',
            'use',
            'phpstan-require-extends',
            'phpstan-require-implements',
            'readonly',
            'var',
            'param',
            'param-out',
            'return',
            'throws',
        ]],
        // 'phpdoc_param_order' => true,
        'phpdoc_separation' => ['groups' => [
            ['see', 'link'],
            ['property', 'property-read', 'property-write', 'phpstan-property', 'phpstan-property-read', 'phpstan-property-write'],
            ['method', 'phpstan-method'],
            ['requires', 'dataProvider', 'backupGlobals'],
            ['template', 'template-covariant'],
            ['extends', 'implements', 'use'],
            ['phpstan-require-extends', 'phpstan-require-implements'],
            ['readonly', 'var', 'param', 'param-out', 'return', 'throws', 'phpstan-var', 'phpstan-param', 'phpstan-return', 'phpstan-assert*', 'phpstan-ignore*', 'disregard'],
            ['phpstan-*'],
        ]],
        'phpdoc_tag_casing' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'single_import_per_statement' => true,
        'single_trait_insert_per_statement' => true,
        'yoda_style' => ['equal' => false, 'identical' => false],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
