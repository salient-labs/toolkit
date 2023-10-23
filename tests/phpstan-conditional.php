<?php declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    return [
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => [
                    'tests/fixtures/Utility/Reflection/MyClassWithDnfTypes.php',
                    'tests/fixtures/Utility/Reflection/MyClassWithUnionsAndIntersections.php',
                ],
            ],
            'ignoreErrors' => [
                [
                    'message' => '#^Property Lkrms\\\\Curler\\\\Curler\:\:\$Handle has unknown class CurlHandle as its type\.$#',
                    'count' => 1,
                    'path' => '../src/Curler/Curler.php',
                ],
            ],
        ]
    ];
}

return [
    'parameters' => [
        'ignoreErrors' => [
            [
                'message' => '#^Strict comparison using \=\=\= between array and false will always evaluate to false\.$#',
                'count' => 1,
                'path' => '../src/Support/ArrayMapper.php',
            ],
        ],
    ]
];
