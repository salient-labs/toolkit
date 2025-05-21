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

$ignoreErrors = \PHP_VERSION_ID >= 80400 ? [
    [
        'identifier' => 'unset.possiblyHookedProperty',
        'paths' => [
            "$dir/src/Toolkit/Sli/Command/Generate/AbstractGenerateCommand.php",
            "$dir/tests/*",
        ],
    ],
] : [
    [
        'message' => '#^Call to method getName\(\) on an unknown class ReflectionConstant\.$#',
        'identifier' => 'class.notFound',
    ],
    [
        'message' => '#^Parameter \$reflectors of method Salient\\\\Utility\\\\Reflect\:\:getNames\(\) has invalid type ReflectionConstant\.$#',
        'identifier' => 'class.notFound',
        'count' => 1,
        'path' => "$dir/src/Toolkit/Utility/Reflect.php",
    ],
];

$excludePaths = \PHP_VERSION_ID >= 80200 ? [] : [
    "$dir/tests/fixtures/Toolkit/Reflection/MyTraitWithConstants.php",
];

if (\PHP_VERSION_ID < 80000) {
    return [
        'includes' => $includes,
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => array_merge([
                    "$dir/tests/fixtures/Toolkit/Reflection/callbacksWithDnfTypes.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/callbacksWithUnionsAndIntersections.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/MyBackedEnum.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithDnfTypes.php",
                    "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithUnionsAndIntersections.php",
                    "$dir/tests/unit/Toolkit/Core/Event/listenerWithDnfType.php",
                    "$dir/tests/unit/Toolkit/Core/Event/listenerWithIntersectionType.php",
                ], $excludePaths),
            ],
            'ignoreErrors' => array_merge([
                [
                    'message' => '#^Parameter \#1 \$ch of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects resource, CurlHandle\|resource given\.$#',
                ],
                [
                    'message' => '#^Call to an undefined method ReflectionClass\<(?:object|\*)\>\:\:(?:isEnum|isReadOnly)\(\)\.$#',
                ],
                [
                    'message' => '#^Call to an undefined method ReflectionClassConstant\:\:isFinal\(\)\.$#',
                ],
                [
                    'message' => '#^Call to an undefined method ReflectionProperty\:\:(?:getDefaultValue|hasDefaultValue|isReadOnly)\(\)\.$#',
                ],
                [
                    'message' => '#^Class Salient\\\\Tests\\\\Reflection\\\\MyBackedEnum not found\.$#',
                ],
                [
                    'message' => '#^Parameter \#2 \$delim_char of function preg_quote expects string, string\|null given\.$#'
                ],
                [
                    'message' => '#^Parameter \#1 \$filter of method ReflectionClass\<T of object\>\:\:getMethods\(\) expects int, int\|null given\.$#',
                    'identifier' => 'argument.type',
                    'count' => 1,
                    'path' => "$dir/src/Toolkit/Core/Reflection/ClassReflection.php",
                ],
                [
                    'message' => '#^Static property Salient\\\\Curler\\\\Curler\:\:\$Handle \(CurlHandle\|resource\|null\) is never assigned CurlHandle so it can be removed from the property type\.$#',
                    'identifier' => 'property.unusedType',
                    'count' => 1,
                    'path' => "$dir/src/Toolkit/Curler/Curler.php",
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
            ], $ignoreErrors),
        ] + $parameters,
    ];
}

return [
    'includes' => $includes,
    'parameters' => [
        'ignoreErrors' => array_merge([
            [
                'message' => '#^Parameter \#1 \$handle of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects CurlHandle, CurlHandle\|resource given\.$#',
            ],
            [
                'message' => "#^Offset 'uri' on array\{timed_out\: bool, blocked\: bool, eof\: bool, unread_bytes\: int, stream_type\: string, wrapper_type\: string, wrapper_data\: mixed, mode\: string, \.\.\.\} on left side of \?\? always exists and is not nullable\.\$#",
                'identifier' => 'nullCoalesce.offset',
            ],
            [
                'message' => '#^Static property Salient\\\\Curler\\\\Curler\:\:\$Handle \(CurlHandle\|resource\|null\) is never assigned resource so it can be removed from the property type\.$#',
                'identifier' => 'property.unusedType',
                'count' => 1,
                'path' => "$dir/src/Toolkit/Curler/Curler.php",
            ],
            [
                'message' => '#^Property Salient\\\\Http\\\\Message\\\\Stream\:\:\$Uri \(string\|null\) is never assigned null so it can be removed from the property type\.$#',
                'identifier' => 'property.unusedType',
                'count' => 1,
                'path' => "$dir/src/Toolkit/Http/Message/Stream.php",
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
        ], $ignoreErrors),
    ] + $parameters,
];
