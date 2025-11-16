<?php declare(strict_types=1);

$includes = [
    sprintf(
        '../phpstan-baseline-%d.%d.neon',
        \PHP_MAJOR_VERSION,
        \PHP_MINOR_VERSION,
    ),
];

$parameters = [
    'phpVersion' => \PHP_VERSION_ID,
    'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

$dir = dirname(__DIR__);

$excludePaths = [
    ...(\PHP_VERSION_ID >= 80200 ? [] : [
        "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithTraitWithConstants.php"
    ]),
    ...(\PHP_VERSION_ID >= 80100 ? [] : [
        "$dir/tests/fixtures/Toolkit/Reflection/MyBackedEnum.php",
    ]),
    ...(\PHP_VERSION_ID >= 80000 ? [] : [
        "$dir/tests/fixtures/Toolkit/Reflection/callbacksWithDnfTypes.php",
        "$dir/tests/fixtures/Toolkit/Reflection/callbacksWithUnionsAndIntersections.php",
        "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithDnfTypes.php",
        "$dir/tests/fixtures/Toolkit/Reflection/MyClassWithUnionsAndIntersections.php",
        "$dir/tests/unit/Toolkit/Core/Event/listenerWithDnfType.php",
        "$dir/tests/unit/Toolkit/Core/Event/listenerWithIntersectionType.php",
    ]),
];

$ignoreErrors = [
    ...(\PHP_VERSION_ID < 80400 ? [] : [
        [
            'identifier' => 'unset.possiblyHookedProperty',
            'paths' => [
                "$dir/src/Toolkit/Sli/Command/Generate/AbstractGenerateCommand.php",
                "$dir/tests/*",
            ],
        ],
    ]),
    ...(\PHP_VERSION_ID >= 80200 ? [] : [
        [
            'message' => '#^Parameter \#1 \$iterator of function iterator_to_array expects Traversable, iterable given\.$#',
        ],
        [
            'message' => '#^Call to an undefined method ReflectionClass\<(?:object|\*)\>\:\:isReadOnly\(\)\.$#',
        ],
    ]),
    ...(\PHP_VERSION_ID >= 80100 ? [] : [
        [
            'message' => '#^Call to an undefined method ReflectionClass\<(?:object|\*)\>\:\:isEnum\(\)\.$#',
        ],
        [
            'message' => '#^Call to an undefined method ReflectionClassConstant\:\:isFinal\(\)\.$#',
        ],
        [
            'message' => '#^Call to an undefined method ReflectionProperty\:\:isReadOnly\(\)\.$#',
        ],
        [
            'message' => '#^Class Salient\\\\Tests\\\\Reflection\\\\MyBackedEnum not found\.$#',
        ],
    ]),
    ...(\PHP_VERSION_ID >= 80000 ? [
        [
            'message' => '#^Parameter \#1 \$handle of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects CurlHandle, CurlHandle\|resource given\.$#',
        ],
    ] : [
        [
            'message' => '#^Parameter \#1 \$ch of function curl_(?:errno|exec|getinfo|reset|setopt(?:_array)?) expects resource, CurlHandle\|resource given\.$#',
        ],
        [
            'message' => '#^Call to an undefined method ReflectionProperty\:\:(?:getDefaultValue|hasDefaultValue|isReadOnly)\(\)\.$#',
        ],
        [
            'message' => '#^Parameter \#2 \$delim_char of function preg_quote expects string, string\|null given\.$#'
        ],
    ]),
];

return [
    'includes' => $includes,
    'parameters' => [
        'excludePaths' => [
            'analyseAndScan' => $excludePaths,
        ],
        'ignoreErrors' => $ignoreErrors,
    ] + $parameters,
];
