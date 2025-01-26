<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase as PHPStanRuleTestCase;

/**
 * @template TRule of Rule
 *
 * @extends PHPStanRuleTestCase<TRule>
 */
abstract class RuleTestCase extends PHPStanRuleTestCase
{
    use PHPStanTestCaseTrait;
}
