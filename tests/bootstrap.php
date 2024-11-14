<?php declare(strict_types=1);

use PHPStan\Testing\PHPStanTestCase;

PHPStanTestCase::getContainer();

require_once __DIR__ . '/fixtures/Toolkit/PHPStan/Core/Rules/TypesAssignedByHasMutatorRuleFailures.php';
require_once __DIR__ . '/fixtures/Toolkit/Utility/Debug/GetCallerFile1.php';
require_once __DIR__ . '/fixtures/Toolkit/Utility/Debug/GetCallerFile2.php';
