<?php declare(strict_types=1);

$includes = [
    sprintf(
        '../phpstan-baseline-%d.%d.neon',
        \PHP_MAJOR_VERSION,
        \PHP_MAJOR_VERSION === 8
            ? 3
            : \PHP_MINOR_VERSION,
    ),
];

$parameters = [
    'phpVersion' => \PHP_VERSION_ID,
    'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

$dir = dirname(__DIR__);

if (\PHP_VERSION_ID < 80000) {
    return [
        'includes' => $includes,
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => [
                    "$dir/tests/fixtures/Toolkit/Reflection/callbacksWithDnfTypes.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/callbacksWithUnionsAndIntersections.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/MyBackedEnum.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithDnfTypes.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithUnionsAndIntersections.php",
                    "$dir/tests/unit/Toolkit/Core/EventDispatcher/listenerWithDnfType.php",
                    "$dir/tests/unit/Toolkit/Core/EventDispatcher/listenerWithIntersectionType.php",
                ],
            ],
            'ignoreErrors' => [
                [
                    'message' => '#^Parameter \#1 \$ch of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects resource, CurlHandle\|resource(\|null)? given\.$#',
                ],
                [
                    'message' => '#^Call to an undefined method ReflectionClass\<object\>\:\:(isEnum|isReadOnly)\(\)\.$#',
                ],
                [
                    'message' => '#^Call to an undefined method ReflectionClassConstant\:\:isFinal\(\)\.$#',
                ],
                [
                    'message' => '#^Call to an undefined method ReflectionProperty\:\:(getDefaultValue|hasDefaultValue|isReadOnly)\(\)\.$#',
                ],
                [
                    'message' => '#^Class Salient\\\\Tests\\\\Reflection\\\\MyBackedEnum not found\.$#',
                ],
                [
                    'message' => '#^Negated boolean expression is always false\.$#',
                    'identifier' => 'booleanNot.alwaysFalse',
                    'count' => 1,
                    'path' => "$dir/src/Toolkit/Core/Introspector.php",
                ],
                [
                    'message' => '#^Method Salient\\\\Utility\\\\Regex\:\:matchAll\(\) should return int but returns int\<0, max\>\|null\.$#',
                    'identifier' => 'return.type',
                    'count' => 1,
                    'path' => "$dir/src/Toolkit/Utility/Regex.php",
                ],
                [
                    'message' => '#^PHPDoc tag @var with type array\<string\> is not subtype of native type non\-empty\-list\<string\>\|false\.$#',
                    'identifier' => 'varTag.nativeType',
                    'count' => 1,
                    'path' => "$dir/src/Toolkit/Utility/Str.php",
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
                'message' => "#^Offset 'uri' on array\{timed_out\: bool, blocked\: bool, eof\: bool, unread_bytes\: int, stream_type\: string, wrapper_type\: string, wrapper_data\: mixed, mode\: string, \.\.\.\} on left side of \?\? always exists and is not nullable\.\$#",
                'identifier' => 'nullCoalesce.offset',
            ],
            [
                'message' => '#^Method Salient\\\\Testing\\\\Core\\\\MockPhpStream\:\:stream_read\(\) never returns false so it can be removed from the return type\.$#',
                'identifier' => 'return.unusedType',
                'count' => 1,
                'path' => "$dir/src/Toolkit/Testing/Core/MockPhpStream.php",
            ],
            [
                'message' => '#^Strict comparison using \=\=\= between array.* and false will always evaluate to false\.$#',
                'identifier' => 'identical.alwaysFalse',
                'count' => 1,
                'path' => "$dir/src/Toolkit/Utility/Arr.php",
            ],
        ],
    ] + $parameters,
];
