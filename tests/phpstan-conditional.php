<?php declare(strict_types=1);

$includes = [
    sprintf('../phpstan-baseline-%d.%d.neon', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

$parameters = [
    'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

if (\PHP_VERSION_ID < 80000) {
    return [
        'includes' => $includes,
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => [
                    'tests/fixtures/Toolkit/Core/Utility/Reflect/MyClassWithDnfTypes.php',
                    'tests/fixtures/Toolkit/Core/Utility/Reflect/MyClassWithUnionsAndIntersections.php',
                ],
            ],
            'ignoreErrors' => [],
        ] + $parameters,
    ];
}

return [
    'includes' => $includes,
    'parameters' => [
        'ignoreErrors' => [
            [
                'message' => '#^Strict comparison using \=\=\= between array and false will always evaluate to false\.$#',
                'count' => 1,
                'path' => '../src/Toolkit/Core/ArrayMapper.php',
            ],
        ],
    ] + $parameters,
];
