<?php declare(strict_types=1);

$includes = [
    sprintf('../phpstan-baseline-%d.%d.neon', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

$parameters = [
    'phpVersion' => \PHP_VERSION_ID,
    'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

if (\PHP_VERSION_ID < 80000) {
    return [
        'includes' => $includes,
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => [
                    'tests/fixtures/Toolkit/Reflection/callbacksWithDnfTypes.php',
                    'tests/fixtures/Toolkit/Reflection/callbacksWithUnionsAndIntersections.php',
                    'tests/fixtures/Toolkit/Reflection/MyClassWithDnfTypes.php',
                    'tests/fixtures/Toolkit/Reflection/MyClassWithUnionsAndIntersections.php',
                    'tests/unit/Toolkit/Core/EventDispatcher/listenerWithDnfType.php',
                    'tests/unit/Toolkit/Core/EventDispatcher/listenerWithIntersectionType.php',
                ],
            ],
            'ignoreErrors' => [
                [
                    'message' => '#^Parameter \#1 \$ch of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects resource, CurlHandle\|resource(\|null)? given\.$#',
                ],
            ],
        ] + $parameters,
    ];
}

return [
    'includes' => $includes,
    'parameters' => [
        'ignoreErrors' => [
            [
                'message' => '#^Parameter \#1 \$handle of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects CurlHandle, CurlHandle\|resource(\|null)? given\.$#',
            ],
            [
                'message' => '#^Strict comparison using \=\=\= between array.* and false will always evaluate to false\.$#',
                'count' => 1,
                'path' => '../src/Toolkit/Utility/Arr.php',
            ],
        ],
    ] + $parameters,
];
